<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Eloquent\Model;

class ComandaNoSql extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'comandas';

    /** Comandas que se descartan (pedido inexistente o de una base anterior) se guardan aquí, no se borran. */
    public const COLECCION_ARCHIVO = 'comandas_archivadas';

    public const ESTADOS_COCINA = ['ABIERTO', 'EN_PREPARACION'];

    protected $fillable = [
        'id_pedido_sql', // Relación con el ID real de SQL Server
        'token_idempotencia', // Identifica al pedido aunque SQL Server reinicie sus IDs
        'id_mesa',
        'id_empleado',
        'estado',
        'detalles_con_modificadores', // Modificadores libres, notas y alérgenos (cada línea lleva su "ronda")
        'tiempos_cocina',
        'ronda_actual', // Número de la ronda que cocina debe preparar ahora
        'ronda_at',     // Hora en que se envió la ronda actual
    ];

    protected $casts = [
        'ronda_at' => 'datetime',
    ];

    public static function llaveBloqueo(int $idPedido): string
    {
        return 'comanda-mongo-' . $idPedido;
    }

    /** Pedido de SQL Server con los datos que identifican su comanda. */
    public static function pedidoSql(int $idPedido): ?object
    {
        return DB::connection('sqlsrv')->table('PEDIDO')
            ->where('id_pedido', $idPedido)
            ->first(['id_pedido', 'token_idempotencia', 'estado', 'fecha_hora']);
    }

    /**
     * ¿Esta comanda es realmente de ese pedido? Los IDs de SQL Server se reinician si la base se
     * recrea, así que el número solo no basta: se compara el token de idempotencia del pedido.
     */
    public function correspondeA(?object $pedido): bool
    {
        if (! $pedido) {
            return false;
        }

        if ($this->token_idempotencia && $pedido->token_idempotencia) {
            return (string) $this->token_idempotencia === (string) $pedido->token_idempotencia;
        }

        // Comandas antiguas sin token: no pueden ser anteriores al pedido
        $creada = $this->created_at;

        return ! $creada || ! $pedido->fecha_hora
            || Carbon::parse($creada)->greaterThanOrEqualTo(Carbon::parse($pedido->fecha_hora)->subHour());
    }

    /**
     * Comanda vigente del pedido. Las que tengan ese número pero sean de otro pedido
     * (base anterior, pedido borrado) se archivan para que no aparezcan en cocina ni se mezclen.
     */
    public static function delPedido(int $idPedido, ?object $pedido = null): ?self
    {
        $pedido ??= self::pedidoSql($idPedido);
        $vigente = null;

        foreach (self::where('id_pedido_sql', $idPedido)->get() as $comanda) {
            if (! $vigente && $comanda->correspondeA($pedido)) {
                $vigente = $comanda;
                continue;
            }

            $comanda->archivar($pedido ? 'Comanda de otro pedido con el mismo número' : 'El pedido no existe en SQL Server');
        }

        return $vigente;
    }

    /** Mueve la comanda a la colección de archivo (con el motivo) y la quita de las comandas activas. */
    public function archivar(string $motivo): void
    {
        $datos = $this->getAttributes();
        $idOriginal = (string) ($datos['_id'] ?? $datos['id'] ?? '');
        unset($datos['_id'], $datos['id']);

        DB::connection('mongodb')->table(self::COLECCION_ARCHIVO)->insert($datos + [
            'id_comanda_original' => $idOriginal,
            'motivo_archivo'      => $motivo,
            'archivada_at'        => now(),
        ]);

        $this->delete();
    }
}
