<?php

namespace App\Jobs;

use App\Models\PedidosShopify;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Exports\PedidosExport;  // Asegúrate de que esta línea esté presente
use Maatwebsite\Excel\Facades\Excel;


class ExportOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    protected $data;
    protected $email;

    public function __construct(array $data, string $email)
    {
        $this->data = $data;
        $this->email = $email;
    }

    public function handle()
    {
        $orders = $this->retrieveData();
        $processedData = $this->processData($orders);

        // Generar el archivo Excel en memoria
        $excelFile = $this->generateExcel($processedData);

        // Enviar el archivo por correo
        $this->sendDownloadLink($this->email, $excelFile);
    }

    private function recursiveWhereHas($query, $relation, $property, $searchTerm)
    {
        if ($searchTerm == "null") {
            $searchTerm = null;
        }
        if (strpos($property, '.') !== false) {

            $nestedRelation = substr($property, 0, strpos($property, '.'));
            $nestedProperty = substr($property, strpos($property, '.') + 1);

            $query->whereHas($relation, function ($q) use ($nestedRelation, $nestedProperty, $searchTerm) {
                $this->recursiveWhereHas($q, $nestedRelation, $nestedProperty, $searchTerm);
            });
        } else {
            $query->whereHas($relation, function ($q) use ($property, $searchTerm) {
                $q->where($property, '=', $searchTerm);
            });
        }
    }

    private function applyCondition($pedidos, $key, $valor, $operator = '=')
    {
        $parts = explode("/", $key);
        $type = $parts[0];
        $filter = $parts[1];

        if (strpos($filter, '.') !== false) {
            $relacion = substr($filter, 0, strpos($filter, '.'));
            $propiedad = substr($filter, strpos($filter, '.') + 1);
            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
        } else {
            if ($type == "equals") {
                $pedidos->where($filter, $operator, $valor);
            } else {
                $pedidos->where($filter, 'LIKE', '%' . $valor . '%');
            }
        }
    }


    // Módulo 1: Recuperar los datos
    protected function retrieveData()
    {
        $startDate = Carbon::createFromFormat('j/n/Y', $this->data['start'])->format('Y-m-d');
        $endDate = Carbon::createFromFormat('j/n/Y', $this->data['end'])->format('Y-m-d');
        $searchTerm = $this->data['search'];
        $filterFields = $searchTerm != "" ? $this->data['or'] : [];
        $map = $this->data['and'];
        $not = $this->data['not'];

        return PedidosShopify::with([
            'operadore.up_users',
            'transportadora',
            'users.vendedores',
            'novedades',
            'pedidoFecha',
            'ruta',
            'subRuta',
            'confirmedBy',
            'pedidoCarrier'
        ])
            ->whereRaw("STR_TO_DATE(fecha_entrega, '%e/%c/%Y') BETWEEN ? AND ?", [$startDate, $endDate])
            ->when($searchTerm, function ($query) use ($filterFields, $searchTerm) {
                $query->where(function ($query) use ($filterFields, $searchTerm) {
                    foreach ($filterFields as $field) {
                        $query->orWhere($field, 'LIKE', '%' . $searchTerm . '%');
                    }
                });
            })
            // ->when($map, function ($query) use ($map) {
            //     foreach ($map as $condition) {
            //         foreach ($condition as $key => $value) {
            //             $query->where($key, $value);
            //         }
            //     }
            // })
            // ->when($not, function ($query) use ($not) {
            //     foreach ($not as $condition) {
            //         foreach ($condition as $key => $value) {
            //             $query->where($key, '!=', $value);
            //         }
            //     }
            // })
            ->when($map, function ($query) use ($map) {
                foreach ($map as $condition) {
                    foreach ($condition as $key => $valor) {
                        $this->applyCondition($query, $key, $valor);
                    }
                }
            })
            ->when($not, function ($query) use ($not) {
                foreach ($not as $condition) {
                    foreach ($condition as $key => $valor) {
                        $this->applyCondition($query, $key, $valor, '!=');
                    }
                }
            })
            ->get();
    }

    // Módulo 2: Procesar los datos
    protected function processData($orders)
    {
        return $orders->map(function ($order) {
            $nombreComercial = $order->users[0]->vendedores[0]->nombre_comercial ?? $order->tienda_temporal;
            $codigoPedido = "{$nombreComercial}-{$order->numero_orden}";
            $subRutaTitulo = $order->subRuta[0]->titulo ?? 'No disponible';
            $transportadora = $order->pedidoCarrier[0]->Carrier->name ?? $order->transportadora[0]->nombre ?? 'No disponible';
            $operador = $order->operadore[0]->up_users[0]->username ?? 'No disponible';
            $rutaTitulo = $order->Ruta[0]->titulo ?? 'No disponible';

            return [
                $order->id,
                $nombreComercial,
                $order->marca_t_i,
                $order->fecha_confirmacion,
                $order->fecha_entrega,
                $order->marca_tiempo_envio,
                $codigoPedido,
                $order->nombre_shipping,
                $order->ciudad_shipping,
                $order->status,
                $transportadora,
                $rutaTitulo,
                $subRutaTitulo,
                $operador,
                $order->observacion,
                $order->comentario,
                $order->estado_interno,
                $order->estado_logistico,
                $order->estado_devolucion,
                $order->costo_transportadora,
                $order->costo_envio
            ];
        });
    }

    // Módulo 3: Generar el archivo Excel en memoria
    protected function generateExcel($processedData)
    {
        $export = new PedidosExport($processedData); // Asegúrate de que tu exportador esté bien configurado
        return Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX); // Generar el archivo en memoria
    }

    // Enviar el archivo Excel por correo
    protected function sendDownloadLink($email, $excelFile)
    {
        Mail::raw("Reporte Completo.", function ($message) use ($email, $excelFile) {
            $message->to($email)
                ->subject('El Reporte Solicitado esta listo')
                ->attachData($excelFile, 'reporte_pedidos.xlsx', [
                    'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]);
        });
    }
}
