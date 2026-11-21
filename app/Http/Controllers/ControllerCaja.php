<?php

namespace App\Http\Controllers;

use App\Models\BitacoraEventoNoSql;
use App\Models\ComandaNoSql;
use App\Models\MetodoPago;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ControllerCaja extends Controller
{
    /** Estados de pedido que se pueden cobrar desde Caja. */
    private const ESTADOS_COBRABLES = ['LISTO', 'ENTREGADO'];

    public function index(Request $request)
    {
        $sql = DB::connection('sqlsrv');
        $empleado = $request->user();

        $pedidos = $sql->table('PEDIDO as p')
            ->join('MESA as m', 'm.id_mesa', '=', 'p.id_mesa')
            ->whereIn('p.estado', self::ESTADOS_COBRABLES)
            ->orderBy('p.fecha_hora')
            ->select('p.id_pedido', 'p.estado', 'p.subtotal', 'p.fecha_hora', 'm.numero_mesa', 'm.zona')
            ->get();

        $detalles = $pedidos->isEmpty() ? collect() : $sql->table('DETALLE_PEDIDO as d')
            ->join('PRODUCTO as pr', 'pr.id_producto', '=', 'd.id_producto')
            ->whereIn('d.id_pedido', $pedidos->pluck('id_pedido'))
            ->orderBy('d.id_pedido')->orderBy('d.num_linea')
            ->select('d.id_pedido', 'pr.nombre_producto', 'd.cantidad', 'd.precio_unitario', 'd.notas')
            ->get()
            ->groupBy('id_pedido');

        $pedidosJs = [];
        foreach ($pedidos as $p) {
            $pedidosJs[$p->id_pedido] = [
                'id'       => (int) $p->id_pedido,
                'mesa'     => (int) $p->numero_mesa,
                'estado'   => $p->estado,
                'subtotal' => (float) $p->subtotal,
                'items'    => ($detalles[$p->id_pedido] ?? collect())->map(fn ($d) => [
                    'nombre'   => $d->nombre_producto,
                    'cantidad' => (int) $d->cantidad,
                    'precio'   => (float) $d->precio_unitario,
                    'notas'    => $d->notas,
                ])->values(),
            ];
        }

        if ($request->ajax()) {
            return view('cajero.facturas.partials.pedidos', compact('pedidos', 'pedidosJs'));
        }

        $cajeros = $sql->table('TURNO_CAJA as t')
            ->join('EMPLEADO as e', 'e.id_empleado', '=', 't.id_empleado')
            ->whereNull('t.fecha_cierre')
            ->when(! $empleado->can('caja.supervisar'), fn ($q) => $q->where('t.id_empleado', $empleado->id_empleado))
            ->select('t.id_turno', 'e.id_empleado', DB::raw("CONCAT(e.nombre, ' ', e.apellido) as nombre"))
            ->get();

        $metodos = MetodoPago::orderBy('nombre_metodo')->get(['id_metodo_pago', 'nombre_metodo']);

        $clientes = $sql->table('CLIENTE')
            ->orderBy('apellido')->orderBy('nombre')
            ->select('id_cliente', DB::raw("CONCAT(nombre, ' ', apellido) as nombre"))
            ->get();

        $supervisaCajas = $empleado->can('caja.supervisar');

        return view('cajero.facturas.index', compact('pedidos', 'pedidosJs', 'cajeros', 'metodos', 'clientes', 'supervisaCajas'));
    }

    public function facturas(Request $request)
    {
        $empleado = $request->user();

        $facturas = DB::connection('sqlsrv')->table('FACTURA as f')
            ->join('PEDIDO as p', 'p.id_pedido', '=', 'f.id_pedido')
            ->join('MESA as m', 'm.id_mesa', '=', 'p.id_mesa')
            ->when(! $empleado->can('caja.supervisar'), fn ($q) => $q->where('f.id_empleado', $empleado->id_empleado))
            ->whereDate('f.fecha_hora', '>=', now()->subDays(7)->toDateString())
            ->orderByDesc('f.fecha_hora')
            ->limit(100)
            ->select('f.numero_factura', 'f.fecha_hora', 'f.total', 'f.estado', 'f.id_turno', 'm.numero_mesa')
            ->get();

        return view('cajero.facturas.emitidas', compact('facturas'));
    }

    private function puedeOperarTurno(Request $request, int $idTurno): bool
    {
        $empleado = $request->user();

        if ($empleado->can('caja.supervisar')) {
            return true;
        }

        return DB::connection('sqlsrv')->table('TURNO_CAJA')
            ->where('id_turno', $idTurno)
            ->where('id_empleado', $empleado->id_empleado)
            ->exists();
    }

    public function cobrar(Request $request, string $id)
    {
        if (! $request->user()->can('caja.supervisar')) {
            $request->merge(['id_cajero' => (int) $request->user()->id_empleado]);
        }

        $datos = $request->validate([
            'id_cajero'                  => ['required', 'integer', 'exists:CAJERO,id_empleado'],
            'id_cliente'                 => ['nullable', 'integer', 'exists:CLIENTE,id_cliente'],
            'descuento'                  => ['nullable', 'numeric', 'between:0,100'],
            'pagos'                      => ['required', 'array', 'min:1'],
            'pagos.*.id_metodo_pago'     => ['required', 'integer', 'exists:METODO_PAGO,id_metodo_pago'],
            'pagos.*.monto'              => ['required', 'numeric', 'decimal:0,2', 'max:99999999.99', 'gt:0'],
            'pagos.*.referencia'         => ['nullable', 'string', 'max:100'],
        ]);

        $idPedido = (int) $id;

        $estado = DB::connection('sqlsrv')->table('PEDIDO')->where('id_pedido', $idPedido)->value('estado');

        if (! in_array($estado, self::ESTADOS_COBRABLES, true)) {
            return response()->json([
                'success' => false,
                'error'   => $estado ? 'El pedido está en estado ' . str_replace('_', ' ', $estado) . ' y no se puede cobrar.' : 'El pedido no existe.',
            ], 409);
        }

        $pagos = collect($datos['pagos'])->map(fn ($p) => [
            'id_metodo_pago' => (int) $p['id_metodo_pago'],
            'monto'          => (float) $p['monto'],
            'referencia'     => $p['referencia'] ?? null,
        ])->values();

        try {
            $resultado = DB::connection('sqlsrv')->select(
                'SET NOCOUNT ON;
                 DECLARE @numero VARCHAR(50);

                 EXEC sp_cobrar_cuenta
                    @id_pedido = ?,
                    @id_cliente = ?,
                    @id_cajero = ?,
                    @descuento = ?,
                    @json_pagos = ?,
                    @numero_factura_out = @numero OUTPUT;

                 SELECT @numero AS numero_factura;',
                [
                    $idPedido,
                    $datos['id_cliente'] ?? null,
                    $datos['id_cajero'],
                    $datos['descuento'] ?? 0,
                    $pagos->toJson(),
                ]
            );

            $numeroFactura = $resultado[0]->numero_factura ?? null;

            if (!$numeroFactura) {
                throw new \Exception('No se pudo obtener el número de factura.');
            }
        } catch (\Throwable $e) {
            Log::error('Error al cobrar pedido', ['pedido' => $idPedido, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => $this->mensajeSql($e)], 422);
        }

        // El cobro en SQL ya se confirmó. Si MongoDB falla, no se revierte la factura.
        try {
            $comanda = ComandaNoSql::where('id_pedido_sql', $idPedido)->first();

            if ($comanda) {
                $comanda->estado = 'FACTURADO';
                $tiempos = $comanda->tiempos_cocina ?? [];
                $tiempos['facturado_at'] = now();
                $comanda->tiempos_cocina = $tiempos;
                $comanda->save();
            }

            BitacoraEventoNoSql::create([
                'evento'     => 'PEDIDO_FACTURADO',
                'detalles'   => 'Pedido #' . $idPedido . ' cobrado. Factura ' . $numeroFactura,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Cobro OK en SQL, pero falló MongoDB', ['pedido' => $idPedido, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'success'        => true,
            'numero_factura' => $numeroFactura,
            'ticket_url'     => route('caja.ticket', $numeroFactura),
        ]);
    }

    public function ticket(Request $request, string $numero)
    {
        $sql = DB::connection('sqlsrv');

        $factura = $sql->table('vw_factura_ticket')->where('numero_factura', $numero)->first();
        abort_if(!$factura, 404);

        $empleado = $request->user();

        abort_if(
            ! $empleado->can('caja.supervisar')
                && ! $sql->table('FACTURA')->where('numero_factura', $numero)->where('id_empleado', $empleado->id_empleado)->exists(),
            403,
            'Solo puedes consultar las facturas que emitiste.'
        );

        $items = $sql->table('FACTURA as f')
            ->join('DETALLE_PEDIDO as d', 'd.id_pedido', '=', 'f.id_pedido')
            ->join('PRODUCTO as p', 'p.id_producto', '=', 'd.id_producto')
            ->where('f.numero_factura', $numero)
            ->orderBy('d.num_linea')
            ->select('p.nombre_producto', 'd.cantidad', 'd.precio_unitario', 'd.notas')
            ->get();

        $pagos = $sql->table('FACTURA as f')
            ->join('PAGO as pg', 'pg.id_factura', '=', 'f.id_factura')
            ->join('METODO_PAGO as mp', 'mp.id_metodo_pago', '=', 'pg.id_metodo_pago')
            ->where('f.numero_factura', $numero)
            ->select('mp.nombre_metodo', 'pg.monto', 'pg.referencia')
            ->get();

        return view('cajero.facturas.ticket', compact('factura', 'items', 'pagos'));
    }

    // ------------------------------------------------------------------
    // CIERRE DE TURNO
    // ------------------------------------------------------------------

    public function resumenTurno(Request $request, string $id)
    {
        if (! $this->puedeOperarTurno($request, (int) $id)) {
            return response()->json(['success' => false, 'error' => 'Solo puedes consultar tu propio turno.'], 403);
        }

        $datos = $this->datosTurno((int) $id);

        if (!$datos) {
            return response()->json(['success' => false, 'error' => 'Turno no encontrado.'], 404);
        }

        return response()->json(['success' => true] + $datos);
    }

    public function cerrarTurno(Request $request, string $id)
    {
        $request->validate([
            'monto_final' => ['required', 'numeric', 'decimal:0,2', 'max:99999999.99', 'min:0'],
        ]);

        $idTurno = (int) $id;

        if (! $this->puedeOperarTurno($request, $idTurno)) {
            return response()->json(['success' => false, 'error' => 'Solo puedes cerrar tu propio turno.'], 403);
        }

        $datos = $this->datosTurno($idTurno);

        if (!$datos) {
            return response()->json(['success' => false, 'error' => 'Turno no encontrado.'], 404);
        }

        if ($datos['cerrado']) {
            return response()->json(['success' => false, 'error' => 'Este turno ya está cerrado.'], 409);
        }

        $montoFinal = round((float) $request->input('monto_final'), 2);

        try {
            $sql = DB::connection('sqlsrv');

            $cerrado = $sql->transaction(function () use ($sql, $idTurno, $montoFinal) {
                $abierto = $sql->table('TURNO_CAJA')
                    ->where('id_turno', $idTurno)
                    ->whereNull('fecha_cierre')
                    ->lockForUpdate()
                    ->exists();

                if (! $abierto) {
                    return false;
                }

                $sql->table('TURNO_CAJA')
                    ->where('id_turno', $idTurno)
                    ->update([
                        'fecha_cierre' => DB::raw('GETDATE()'),
                        'monto_final'  => $montoFinal,
                    ]);

                return true;
            });

            if (! $cerrado) {
                return response()->json(['success' => false, 'error' => 'Este turno ya está cerrado.'], 409);
            }
        } catch (\Throwable $e) {
            Log::error('Error al cerrar turno', ['turno' => $idTurno, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => $this->mensajeSql($e)], 422);
        }

        $diferencia = round($montoFinal - $datos['esperado'], 2);

        try {
            BitacoraEventoNoSql::create([
                'evento'     => 'TURNO_CERRADO',
                'detalles'   => 'Turno #' . $idTurno . ' de ' . $datos['cajero'] .
                    ' cerrado. Esperado: ' . $datos['esperado'] .
                    ', contado: ' . $montoFinal . ', diferencia: ' . $diferencia,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Turno cerrado, pero falló la bitácora en MongoDB', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success'    => true,
            'esperado'   => $datos['esperado'],
            'contado'    => $montoFinal,
            'diferencia' => $diferencia,
        ]);
    }

    /** Totales de un turno: ventas, cobros por método y efectivo esperado en caja. */
    private function datosTurno(int $idTurno): ?array
    {
        $sql = DB::connection('sqlsrv');

        $turno = $sql->table('TURNO_CAJA as t')
            ->join('EMPLEADO as e', 'e.id_empleado', '=', 't.id_empleado')
            ->where('t.id_turno', $idTurno)
            ->select('t.id_turno', 't.fecha_apertura', 't.fecha_cierre', 't.monto_inicial',
                DB::raw("CONCAT(e.nombre, ' ', e.apellido) as cajero"))
            ->first();

        if (!$turno) {
            return null;
        }

        $totales = $sql->table('FACTURA')
            ->where('id_turno', $idTurno)
            ->where('estado', 'COBRADA')
            ->selectRaw('COUNT(*) as facturas, COALESCE(SUM(total), 0) as ventas, COALESCE(SUM(vuelto), 0) as vuelto')
            ->first();

        $metodos = $sql->table('PAGO as pg')
            ->join('FACTURA as f', 'f.id_factura', '=', 'pg.id_factura')
            ->join('METODO_PAGO as mp', 'mp.id_metodo_pago', '=', 'pg.id_metodo_pago')
            ->where('f.id_turno', $idTurno)
            ->where('f.estado', 'COBRADA')
            ->groupBy('mp.nombre_metodo')
            ->select('mp.nombre_metodo as nombre', DB::raw('SUM(pg.monto) as total'))
            ->get()
            ->map(fn ($m) => ['nombre' => $m->nombre, 'total' => round((float) $m->total, 2)])
            ->values();

        // Efectivo: métodos cuyo nombre contiene "efectivo"
        $efectivo = round((float) $metodos
            ->filter(fn ($m) => stripos($m['nombre'], 'efectivo') !== false)
            ->sum('total'), 2);

        $vuelto  = round((float) $totales->vuelto, 2);
        $inicial = round((float) $turno->monto_inicial, 2);

        return [
            'id_turno'       => (int) $turno->id_turno,
            'cajero'         => $turno->cajero,
            'apertura'       => Carbon::parse($turno->fecha_apertura)->format('d/m/Y H:i'),
            'cerrado'        => $turno->fecha_cierre !== null,
            'monto_inicial'  => $inicial,
            'facturas'       => (int) $totales->facturas,
            'ventas'         => round((float) $totales->ventas, 2),
            'metodos'        => $metodos,
            'efectivo'       => $efectivo,
            'vuelto'         => $vuelto,
            'esperado'       => round($inicial + $efectivo - $vuelto, 2),
        ];
    }
}
