<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TransportStats;
use Carbon\Carbon;
use Illuminate\Http\Request;


class TransportStatsAPIController extends Controller
{

    public function index()
    {
        $pedidos = TransportStats::all();
        return response()->json($pedidos);
    }

    public function fetchDataByDate(Request $request)
    {
        // Captura la fecha del request
        $datef = $request->input('date');
        $date = Carbon::createFromFormat('j/n/Y', $datef);

        // Buscar el último registro basado en la fecha dada para efficiency_day_date
        $lastDayDate = TransportStats::where('efficiency_day_date', '<=', $date)
            ->orderBy('efficiency_day_date', 'desc')
            ->value('efficiency_day_date');
        // ->value('efficiency_day_date');

        // Si no hay datos para efficiency_day_date, retornar un error o una respuesta vacía
        if (!$lastDayDate) {
            return response()->json(['error' => 'No data found for the given date.']);
        }

        // Obtener los datos de la tabla basado solo en la fecha más cercana obtenida
        $data = TransportStats::where('efficiency_day_date', $lastDayDate)->get();

        // Crear el arreglo deseado
        $result = [];
        foreach ($data as $row) {
            $key = $row->transport_name . '-' . $row->transport_id;

            // Aquí se crea la estructura solicitada
            $routeItem = [
                'name' => $row->route_name ?? '',
                'month_value' => $row->transport_stats_month ?? '0',
                'daily_value' => $row->transport_stats_day ?? '0',
                'monthly_counter' => $row->monthly_counter ?? '0',
                'daily_counter' => $row->daily_counter ?? '0',
                'month_date' => $row->efficiency_month_date ?? '0',
                'month_day' => $row->efficiency_day_date ?? '0',
            ];

            $result[$key][] = $routeItem;
        }

        // Retornar el arreglo
        return response()->json($result);
    }

    public function fetchDataByDate2(Request $request)
    {

        $data = TransportStats::all();

        // Crear el arreglo deseado
        $result = [];
        foreach ($data as $row) {
            $key = $row->transport_name . '-' . $row->transport_id;

            // Aquí se crea la estructura solicitada, asignando 0 si los valores son null
            $routeItem = [
                'name' => $row->route_name ?? '',
                'month_value' => $row->transport_stats_month ?? '0',
                'daily_value' => $row->transport_stats_day ?? '0',
                'monthly_counter' => $row->monthly_counter ?? '0',
                'daily_counter' => $row->daily_counter ?? '0',
                'month_date' => $row->efficiency_month_date ?? '0',
                'month_day' => $row->efficiency_day_date ?? '0',
            ];

            $result[$key][] = $routeItem;
        }

        // Retornar el arreglo
        return response()->json($result);
    }

    public function fetchDataByDate3(Request $request)
    {
        $data = TransportStats::all();
    
        $result = [];
        $totals = [];
    
        foreach ($data as $row) {
            $routeKey = $row->route_name;
            $transportKey = $row->transport_name . '-' . $row->transport_id;
    
            $transportItem = [
                'name' => $row->transport_name . '-' . $row->transport_id ?? '',
                'month_value' => $row->transport_stats_month ?? '0',
                'daily_value' => $row->transport_stats_day ?? '0',
                'monthly_counter' => $row->monthly_counter ?? '0',
                'daily_counter' => $row->daily_counter ?? '0',
                'month_date' => $row->efficiency_month_date ?? '0',
                'day_date' => $row->efficiency_day_date ?? '0',
            ];
    
            // Suma parcial para cada ruta
            $totals[$routeKey]['month_value_total'] = ($totals[$routeKey]['month_value_total'] ?? 0) + $transportItem['month_value'];
            $totals[$routeKey]['monthly_counter_total'] = ($totals[$routeKey]['monthly_counter_total'] ?? 0) + $transportItem['monthly_counter'];
    
            // Si no existe el key de la ruta, inicializamos el array
            if (!isset($result[$routeKey])) {
                $result[$routeKey] = [];
            }
    
            $result[$routeKey][] = $transportItem;
        }
    
        // Ahora añadimos las sumatorias a cada ítem de transporte dentro de cada ruta
        foreach ($result as $routeKey => &$routeItems) {
            foreach ($routeItems as &$item) {
                $item['month_value_total'] = round($totals[$routeKey]['month_value_total'], 2);
                $item['monthly_counter_total'] = $totals[$routeKey]['monthly_counter_total'];
            }
        }
    
        return response()->json($result);
    }
    
    



}

?>