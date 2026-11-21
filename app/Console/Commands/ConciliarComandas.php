<?php

namespace App\Console\Commands;

use App\Models\ComandaNoSql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Compara las comandas de MongoDB con los pedidos de SQL Server (la fuente de verdad):
 *  - Comandas cuyo pedido no existe, o que son de otro pedido con el mismo número
 *    (la base de SQL Server se recreó): se archivan en "comandas_archivadas".
 *  - Comandas con un estado distinto al del pedido: se corrige el estado.
 *  - Pedidos sin comanda: se informan (cocina los reconstruye al abrir la pantalla).
 */
class ConciliarComandas extends Command
{
    protected $signature = 'restaurante:conciliar-comandas
        {--simular : Solo muestra lo que haría, sin cambiar nada}
        {--reconstruir : Crea en MongoDB las comandas de los pedidos que no tienen}';

    protected $description = 'Concilia las comandas de MongoDB con los pedidos de SQL Server (quita comandas fantasma)';

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');

        $pedidos = DB::connection('sqlsrv')->table('PEDIDO')
            ->get(['id_pedido', 'token_idempotencia', 'estado', 'fecha_hora'])
            ->keyBy(fn ($p) => (int) $p->id_pedido);

        $comandas = ComandaNoSql::orderBy('created_at')->get();
        $vigentes = [];
        $archivadas = 0;
        $corregidas = 0;

        foreach ($comandas as $comanda) {
            $id = (int) $comanda->id_pedido_sql;
            $pedido = $pedidos->get($id);

            if (! $comanda->correspondeA($pedido) || isset($vigentes[$id])) {
                $motivo = ! $pedido ? 'El pedido no existe en SQL Server'
                    : (isset($vigentes[$id]) ? 'Comanda duplicada del mismo pedido' : 'Comanda de otro pedido con el mismo número');

                $this->line(sprintf('  archivar  pedido #%-5d %-15s %s', $id, $comanda->estado, $motivo));
                $simular || $comanda->archivar($motivo);
                $archivadas++;
                continue;
            }

            $vigentes[$id] = true;

            if ($comanda->estado !== $pedido->estado) {
                $this->line(sprintf('  corregir  pedido #%-5d %s -> %s', $id, $comanda->estado, $pedido->estado));
                if (! $simular) {
                    $comanda->estado = $pedido->estado;
                    $comanda->save();
                }
                $corregidas++;
            }
        }

        $sinComanda = $pedidos->keys()->reject(fn ($id) => isset($vigentes[$id]))->values();

        $this->newLine();
        $this->info(($simular ? '[SIMULACIÓN] ' : '') . "Comandas revisadas: {$comandas->count()} · archivadas: {$archivadas} · estado corregido: {$corregidas}");

        if ($sinComanda->isEmpty()) {
            return self::SUCCESS;
        }

        $this->warn('Pedidos de SQL Server sin comanda en MongoDB: ' . $sinComanda->implode(', '));

        if (! $this->option('reconstruir') || $simular) {
            $this->line('Use --reconstruir para crearlas desde SQL Server.');

            return self::SUCCESS;
        }

        foreach ($sinComanda as $id) {
            $this->reconstruir($pedidos->get($id));
        }

        $this->info('Comandas reconstruidas: ' . $sinComanda->count());

        return self::SUCCESS;
    }

    /** Crea la comanda de un pedido a partir de SQL Server (con su estado real). */
    private function reconstruir(object $pedido): void
    {
        $sql = DB::connection('sqlsrv');
        $p = $sql->table('PEDIDO')->where('id_pedido', $pedido->id_pedido)->first();
        $inicio = \Carbon\Carbon::parse($p->fecha_hora);

        ComandaNoSql::create([
            'id_pedido_sql'              => (int) $p->id_pedido,
            'token_idempotencia'         => $p->token_idempotencia,
            'id_mesa'                    => (int) $p->id_mesa,
            'id_empleado'                => (int) $p->id_empleado,
            'estado'                     => $p->estado,
            'detalles_con_modificadores' => $sql->table('DETALLE_PEDIDO')->where('id_pedido', $p->id_pedido)->orderBy('num_linea')->get()
                ->map(fn ($d) => [
                    'num_linea'   => (int) $d->num_linea,
                    'id_producto' => (int) $d->id_producto,
                    'cantidad'    => (int) $d->cantidad,
                    'notas'       => $d->notas ?? '',
                    'ronda'       => 1,
                ])->values()->all(),
            'tiempos_cocina'             => ['enviado_at' => $inicio, 'preparando_at' => null, 'listo_at' => null],
            'ronda_actual'               => 1,
            'ronda_at'                   => $inicio,
        ]);

        $this->line("  reconstruida comanda del pedido #{$p->id_pedido} ({$p->estado})");
    }
}
