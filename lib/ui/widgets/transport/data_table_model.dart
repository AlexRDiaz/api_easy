import 'package:data_table_2/data_table_2.dart';
import 'package:flutter/material.dart';

class DataTableModelPrincipal extends StatelessWidget {
  final List<DataColumn2> columns;
  // final List data;
  final List<DataRow> rows;
  // List<DataCell>getRows;

  DataTableModelPrincipal({
    super.key,
    required this.columns,
    // required this.data,
    required this.rows,
  });

  @override
  Widget build(BuildContext context) {
    return DataTable2(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: const BorderRadius.all(Radius.circular(4)),
        border: Border.all(color: Colors.blueGrey),
        boxShadow: const [
          BoxShadow(
            color: Colors.grey,
            offset: Offset(0, 2), // Desplazamiento en X e Y de la sombra
            blurRadius: 4, // Radio de desenfoque de la sombra
            spreadRadius: 1, // Extensión de la sombra
          ),
        ],
      ),
      headingRowHeight: 53,
      showBottomBorder: true,
      headingTextStyle:
          const TextStyle(fontWeight: FontWeight.bold, color: Colors.black),
      dataTextStyle: const TextStyle(
          fontSize: 12, fontWeight: FontWeight.bold, color: Colors.black),
      columnSpacing: 12,
      horizontalMargin: 12,
      minWidth: 2500,
      columns: columns,
      rows: rows,
    );
  }
}
