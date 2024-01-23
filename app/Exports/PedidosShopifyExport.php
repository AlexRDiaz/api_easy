<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PedidosShopifyExport implements FromCollection, WithHeadings
{
    use Exportable;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            "Fecha de Ingreso",
            "Fecha de Entrega",
            "Codigo",
            "Nombre",
            "Ciudad",
            "Direccion",
            "Telefono",
            "Cantidad",
            "Producto",
            "Producto Extra",
            "Precio Total",
            "Comentario",
            "Estado de Confirmacion",
            "Status",
            "Estado de Entrega",
            "Estado Devolucion",
            "Costo Transporte",
            "Costo Devolucion"

            // Otras columnas aquí...
        ];
    }

    public function mapArraybleRow($row): array
    {
        return [
            $row->fecha_ingreso,
            $row->fecha_entrega,
            // Mapea las otras propiedades aquí...
        ];
    }
}
