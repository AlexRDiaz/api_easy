<?php

namespace App\Http\Controllers\API;

use App\Exports\MyExport;
use App\Exports\PedidosShopifyExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GenerateReport;
use App\Models\PedidosShopify;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
//use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel;
use Ramsey\Uuid\Uuid;

class GenerateReportAPIController extends Controller
{
    public function index()
    {
        $generateReports = GenerateReport::all();
        return response()->json(['data' => $generateReports], 200);
    }

    public function show($id)
    {
        $generateReport = GenerateReport::find($id);

        if (!$generateReport) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        return response()->json(['data' => $generateReport], 200);
    }

    public function getBySeller($sellerId)
    {
        $generateReport = GenerateReport::where("id_master", $sellerId)->get();

        if (!$generateReport) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        return response()->json(['data' => $generateReport], 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'fecha' => 'required',
            'archivo' => 'required',
            'id_master' => 'required',
        ]);

        $generateReport = GenerateReport::create($request->all());

        return response()->json(['data' => $generateReport], 201);
    }

    public function update(Request $request, $id)
    {
        $generateReport = GenerateReport::find($id);

        if (!$generateReport) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $request->validate([
            'fecha' => 'required',
            'archivo' => 'required',
            'id_master' => 'required',
        ]);

        $generateReport->update($request->all());

        return response()->json(['data' => $generateReport], 200);
    }

    public function destroy($id)
    {
        $generateReport = GenerateReport::find($id);

        if (!$generateReport) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $generateReport->delete();

        return response()->json(['message' => 'Report deleted'], 204);
    }

    public function generateExcel(Request $request)
    {

        // Realiza una solicitud a la API externa para obtener los datos filtrados

        $data = $request->json()->all();
        $idMaster = $data['id_master'];

        $startDate = $data['start'];
        $endDate = $data['end'];
        $generateDate = $data['generate_date'];
        $startDateFormatted = Carbon::createFromFormat('j/n/Y', $startDate)->format('Y-m-d');
        $endDateFormatted = Carbon::createFromFormat('j/n/Y', $endDate)->format('Y-m-d');
        $and = $data['and'];

        $status = $data['status'];
        $internal = $data['internal'];

        $pedidos = PedidosShopify::select('marca_t_i', 'fecha_entrega', DB::raw('concat(tienda_temporal, "-", numero_orden) as codigo'), 'nombre_shipping', 'ciudad_shipping', 'direccion_shipping', 'telefono_shipping', 'cantidad_total', 'producto_p', 'producto_extra', 'precio_total', 'comentario', 'estado_interno', 'status', 'estado_logistico', 'estado_devolucion', 'costo_envio', 'costo_devolucion')->with(['operadore.up_users', 'transportadora', 'users.vendedores', 'novedades', 'pedidoFecha', 'ruta', 'subRuta'])
            ->whereRaw("STR_TO_DATE(fecha_entrega, '%e/%c/%Y') BETWEEN ? AND ?", [$startDateFormatted, $endDateFormatted])->where((function ($pedidos) use ($and) {
                foreach ($and as $condition) {
                    foreach ($condition as $key => $valor) {
                        if (strpos($key, '.') !== false) {
                            $relacion = substr($key, 0, strpos($key, '.'));
                            $propiedad = substr($key, strpos($key, '.') + 1);
                            $this->recursiveWhereHas($pedidos, $relacion, $propiedad, $valor);
                        } else {
                            $pedidos->where($key, '=', $valor);
                        }
                    }
                }
            }));
        if (!empty($status)) {
            $pedidos->whereIn('status', $status);
        }
        if (!empty($internal)) {
            $pedidos->whereIn('estado_interno', $internal);
        }
        $response = $pedidos->get();

        if (!empty($response)) {
          //  $export = new PedidosShopifyExport($response);
            $uuid = Uuid::uuid4(); // Genera un UUID aleatorio de tipo 4 (aleatorio).
            $fileName= $uuid->toString() . '.xlsx';
           // $export->store($url, 'sales');
           $excelFile = Excel::download(new PedidosShopifyExport($response), $fileName);
           Storage::disk('sales')->put($fileName, $excelFile);
//$m=Storage::disk('sales')->url($fileName);

           // $filePath = Storage::url($export); // Utiliza Storage::url para obtener la URL correcta del archivo.

            // $report = new GenerateReport();
            // $report->fecha = $generateDate;
            // $report->archivo =  $filePath; // Almacena solo la ruta relativa del archivo en la base de datos.
            // $report->id_master = $idMaster;
            // $report->save();
            return Storage::disk('sales')->download($fileName);
         // return response()->json(['message' => 'Reporte generado','download_url' => $m,'location_url' =>""]);
        } else {
            return response()->json(['message' => 'Sin datos']);
        }
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
}
