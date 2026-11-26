@if ($sinMongo ?? false)
    <div class="kds-aviso-mongo" role="alert">
        <i class="ti ti-alert-triangle"></i>
        MongoDB no está disponible. La cola se muestra desde SQL Server en modo de solo lectura; los cambios de estado se habilitan cuando vuelva la conexión.
    </div>
@endif
@forelse($comandas as $comanda)
    @php
        $esAbierto = $comanda->estado === 'ABIERTO';
        $claseCard = $esAbierto ? 'estado-abierto' : 'estado-preparacion';
        $claseBadge = $esAbierto ? 'badge-abierto' : 'badge-preparacion';

        // Todas las rondas que cocina aún no terminó (no solo la última)
        $lineas = $comanda->lineasPendientes();
        $rondas = $lineas->map(fn ($d) => (int) ($d['ronda'] ?? 1))->unique()->sort()->values();
        $variasRondas = $rondas->count() > 1;
        $etiquetaRonda = $variasRondas
            ? 'Rondas ' . $rondas->first() . '–' . $rondas->last()
            : (($rondas->first() ?? 1) > 1 ? 'Ronda ' . $rondas->first() : null);
        $horaEnvio = $comanda->ronda_at ?? $comanda->created_at;
        $minutos = (int) $horaEnvio->diffInMinutes(now());
        $claseTiempo = $minutos >= 20 ? 'kds-hora--critica' : ($minutos >= 10 ? 'kds-hora--atencion' : '');
    @endphp

    <div class="kds-card {{ $claseCard }}" data-comanda="{{ $comanda->_id }}" data-estado="{{ $comanda->estado }}">

        <div class="kds-card-header">
            <div class="kds-header-fila">
                <div class="kds-mesa-info">
                    <i class="ti ti-armchair"></i>
                    <div>
                        <span class="kds-etiqueta">Mesa</span>
                        <h3 class="kds-mesa-nombre">Mesa {{ $numerosMesa[$comanda->id_mesa] ?? $comanda->id_mesa }}</h3>
                    </div>
                </div>

                <div class="kds-pedido">
                    <span class="kds-etiqueta">Pedido SQL</span>
                    <span class="kds-pedido-num">#{{ $comanda->id_pedido_sql }}</span>
                </div>
            </div>

            <div class="kds-header-pie">
                <div class="kds-badges">
                    <span class="kds-badge {{ $claseBadge }}">
                        {{ str_replace('_', ' ', $comanda->estado) }}
                    </span>
                    @if ($etiquetaRonda)
                        <span class="kds-ronda">{{ $etiquetaRonda }}</span>
                    @endif
                </div>
                <span class="kds-hora num {{ $claseTiempo }}">
                    <i class="ti ti-clock"></i> {{ $horaEnvio->format('H:i:s') }} · {{ $minutos }} min
                </span>
            </div>
        </div>

        <div class="kds-card-body">
            <h4 class="kds-body-titulo">Productos solicitados</h4>

            <ul class="kds-lista">
                @foreach ($lineas as $detalle)
                    <li class="kds-item">
                        <div class="kds-item-fila">
                            <span>
                                @if ($variasRondas)
                                    <span class="kds-ronda">R{{ $detalle['ronda'] ?? 1 }}</span>
                                @endif
                                {{ $nombresProductos[$detalle['id_producto']] ?? 'Producto #' . $detalle['id_producto'] }}
                            </span>
                            <span class="kds-cantidad">x{{ $detalle['cantidad'] }}</span>
                        </div>

                        @if (!empty($detalle['notas']))
                            <p class="kds-nota">
                                <i class="ti ti-alert-circle"></i> Nota: {{ $detalle['notas'] }}
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="kds-card-footer">
            @if ($sinMongo ?? false)
                <p class="kds-solo-lectura"><i class="ti ti-lock"></i> Solo lectura mientras MongoDB no responde</p>
            @elseif ($comanda->estado === 'ABIERTO')
                <button type="button" data-cambiar-estado="EN_PREPARACION" data-id="{{ $comanda->_id }}"
                    class="kds-btn kds-btn-iniciar">
                    <i class="ti ti-flame"></i>
                    Iniciar preparación
                </button>
            @elseif($comanda->estado === 'EN_PREPARACION')
                <button type="button" data-cambiar-estado="LISTO" data-id="{{ $comanda->_id }}"
                    class="kds-btn kds-btn-listo">
                    <i class="ti ti-check"></i>
                    Marcar como listo
                </button>
            @endif
        </div>

    </div>
@empty
    <div class="kds-vacio">
        <i class="ti ti-coffee"></i>
        <p class="kds-vacio-titulo">No hay comandas pendientes</p>
        <p class="kds-vacio-texto">Las nuevas comandas aparecerán aquí automáticamente.</p>
    </div>
@endforelse
