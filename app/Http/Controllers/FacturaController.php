<?php

namespace App\Http\Controllers;

use App\Models\BitacoraEventoNoSql;
use App\Models\Factura;
use App\Support\Sp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacturaController extends Controller
{
    // ---------- Listado con filtros (estado y fecha) ----------
    public function index(Request $request)
    {
        $items = Factura::with(['cliente', 'cajero'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('fecha'), fn ($q) => $q->whereDate('fecha_hora', $request->fecha))
            ->orderByDesc('fecha_hora')
            ->paginate(15);

        return view('admin.facturas.index', compact('items'));
    }

    // ---------- Detalle de la factura con sus pagos ----------
    public function show($id)
    {
        $item = Factura::with(['cliente', 'cajero', 'pagos.metodo', 'anuladaPor'])->findOrFail($id);

        return view('admin.facturas.show', compact('item'));
    }

    // ---------- Anular factura (solo si está cobrada; exige motivo y queda registrado quién y cuándo) ----------
    public function anular(Request $request, $id)
    {
        $factura = Factura::findOrFail($id);

        if ($factura->estado !== 'COBRADA') {
            return back()->with('error', 'Esta factura ya está anulada.');
        }

        $datos = $request->validate([
            'motivo_anulacion' => ['required', 'string', 'max:255'],
        ]);

        // En SQL Server la anulación la hace sp_anular_factura: valida el permiso, bloquea la
        // factura (UPDLOCK, HOLDLOCK) y la marca ANULADA en una transacción. Si otra persona
        // la anuló un instante antes, el procedimiento responde con su THROW.
        if (Sp::disponibles()) {
            try {
                Sp::salida('sp_anular_factura', [
                    'id_factura'  => (int) $factura->id_factura,
                    'id_empleado' => (int) auth()->id(),
                    'motivo'      => $datos['motivo_anulacion'],
                ], 'numero_factura', 'VARCHAR(50)');
            } catch (\Throwable $e) {
                return back()->with('error', $this->mensajeSql($e));
            }

            $this->registrarAnulacionEnBitacora($factura, $datos['motivo_anulacion']);

            return redirect()->route('admin.facturas.show', $factura->id_factura)->with('success', 'Factura anulada correctamente.');
        }

        // Pruebas automáticas (SQLite, sin procedimientos): misma regla con un UPDATE condicionado
        $anulada = DB::connection('sqlsrv')->transaction(function () use ($factura, $datos) {
            $cobrada = Factura::where('id_factura', $factura->id_factura)
                ->where('estado', 'COBRADA')
                ->lockForUpdate()
                ->exists();

            if (! $cobrada) {
                return false;
            }

            Factura::where('id_factura', $factura->id_factura)->update([
                'estado'           => 'ANULADA',
                'motivo_anulacion' => $datos['motivo_anulacion'],
                'anulada_por'      => auth()->id(),
                'fecha_anulacion'  => DB::raw('GETDATE()'),
            ]);

            return true;
        });

        if (! $anulada) {
            return back()->with('error', 'Esta factura ya está anulada.');
        }

        $this->registrarAnulacionEnBitacora($factura, $datos['motivo_anulacion']);

        return redirect()->route('admin.facturas.show', $factura->id_factura)->with('success', 'Factura anulada correctamente.');
    }

    // ---------- Evento en MongoDB: si Mongo falla, la anulación en SQL Server ya quedó firme ----------
    private function registrarAnulacionEnBitacora(Factura $factura, string $motivo): void
    {
        try {
            BitacoraEventoNoSql::create([
                'evento'     => 'FACTURA_ANULADA',
                'detalles'   => 'Factura ' . $factura->numero_factura . ' anulada. Motivo: ' . $motivo,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Factura anulada en SQL, pero falló la bitácora en MongoDB', ['error' => $e->getMessage()]);
        }
    }
}
