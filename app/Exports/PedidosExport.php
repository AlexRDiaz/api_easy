<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PedidosExport implements FromCollection, WithHeadings, WithMapping
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // Devuelve los datos procesados, tal como los has mapeado en el Job
        return collect($this->data);
    }

    /**
     * Define los encabezados de las columnas
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID Pedido', 'Tienda', 'Fecha Ingreso Pedido', 'Fecha de Confirmación', 'Fecha Entrega',
            'Marca Tiempo Envio', 'Código', 'Nombre Cliente', 'Ciudad', 'Status', 'Transportadora',
            'Ruta', 'Subruta', 'Operador', 'Observación', 'Comentario', 'Estado Interno', 
            'Estado Logístico', 'Estado Devolución', 'Costo Transportadora', 'Costo EasyEcommerce'
        ];
    }

    /**
     * Mapea los datos para cada fila
     */
    public function map($order): array
    {
        return [
            $order[0], // ID Pedido
            $order[1], // Tienda
            $order[2], // Fecha Ingreso Pedido
            $order[3], // Fecha de Confirmación
            $order[4], // Fecha Entrega
            $order[5], // Marca Tiempo Envio
            $order[6], // Código
            $order[7], // Nombre Cliente
            $order[8], // Ciudad
            $order[9], // Status
            $order[10], // Transportadora
            $order[11], // Ruta
            $order[12], // Subruta
            $order[13], // Operador
            $order[14], // Observación
            $order[15], // Comentario
            $order[16], // Estado Interno
            $order[17], // Estado Logístico
            $order[18], // Estado Devolución
            $order[19], // Costo Transportadora
            $order[20]  // Costo EasyEcommerce
        ];
    }
}
