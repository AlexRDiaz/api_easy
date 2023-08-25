// ignore_for_file: unnecessary_null_comparison, use_build_context_synchronously


import 'package:calendar_date_picker2/calendar_date_picker2.dart';
import 'package:flutter/material.dart';
import 'package:data_table_2/data_table_2.dart';
import 'package:frontend/connections/connections.dart';
import 'package:frontend/helpers/responsive.dart';
import 'package:frontend/main.dart';
import 'package:frontend/ui/transport/delivery_status_transport/Opcion.dart';
import 'package:frontend/ui/widgets/OptionsWidget.dart';
import 'package:frontend/ui/transport/delivery_status_transport/delivery_details.dart';
import 'package:frontend/ui/transport/delivery_status_transport/scanner_delivery_status_transport.dart';
import 'package:frontend/ui/transport/my_orders_prv/controllers/controllers.dart';
import 'package:frontend/ui/widgets/box_values_transport.dart';
import 'package:frontend/ui/widgets/loading.dart';
import 'package:intl/intl.dart';
import 'package:number_paginator/number_paginator.dart';

import 'package:frontend/ui/widgets/transport/data_table_model.dart';

class DeliveryStatusTransport extends StatefulWidget {
  const DeliveryStatusTransport({super.key});

  @override
  State<DeliveryStatusTransport> createState() =>
      _DeliveryStatusTransportState();
}

class _DeliveryStatusTransportState extends State<DeliveryStatusTransport> {
  final MyOrdersPRVTransportControllers _controllers =
      MyOrdersPRVTransportControllers();
  List data = [];
  bool search = false;
  bool sort = false;
  String currentValue = "";
  int total = 0;
  int entregados = 0;
  int noEntregados = 0;
  int conNovedad = 0;
  int reagendados = 0;
  int enRuta = 0;
  int programado = 0;
  double totalValoresRecibidos = 0;
  double costoTransportadora = 0;
  bool isFirst = true;
  int counterLoad = 0;
  String transporterOperator = 'TODO';
  int currentPage = 1;
  int pageSize = 75;
  int pageCount = 100;
  bool isLoading = false;
  Map dataCounters = {};
  Map valuesTransporter = {};
  var sortField = "";
  Color currentColor = const Color.fromARGB(255, 108, 108, 109);
  var arrayDateRanges = [];

  List<String> listOperators = [
    'TODO',
    'Omar',
    'Wellintong',
    'Eduardo',
    'Patricio',
    'Jeison',
    'Raul',
    'Julio',
    'Byron',
    'Javier',
    'Fernando',
    'Guido',
    'Kevin',
    'Nestor',
    'Milton Simbaña',
    'Noe Ordoñez',
    'Paul',
    'jose',
    'Ivan Ibarra',
    'Fernando Laina',
    'Jhonny Ati'
  ];
  List populate = [
    'transportadora.operadores.user',
    'pedido_fecha',
    'sub_ruta',
    'operadore',
    'operadore.user',
    'users',
    'users.vendedores',
    'novedades'
  ];
  List arrayFiltersAnd = [];
  List arrayFiltersOr = [
    'fecha_entrega',
    'numero_orden',
    'nombre_shipping',
    'ciudad_shipping',
    'direccion_shipping',
    'telefono_shipping',
    'cantidad_total',
    'producto_p',
    'producto_extra',
    'precio_total',
    'observacion',
    'comentario',
    'status',
    'tipo_Pago',
    'marca_t_d',
    'marca_t_d_l',
    'marca_t_d_t',
    'marca_t_i',
    'estado_pagado',
  ];

  TextEditingController operadorController =
      TextEditingController(text: "TODO");

  NumberPaginatorController paginatorController = NumberPaginatorController();

  @override
  Future<void> didChangeDependencies() async {
    initializeDates();
    loadData(context);
    // getOperatorsList();
    super.didChangeDependencies();
  }

  loadData(context) async {
    isLoading = true;
    currentPage = 1;

    try {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        getLoadingModal(context, false);
      });

      setState(() {
        search = false;
      });

      var response = await Connections()
          .getOrdersForSellerStateSearchForDateTransporterLaravel(
              populate,
              arrayFiltersAnd,
              arrayFiltersOr,
              currentPage,
              pageSize,
              _controllers.searchController.text,
              sortField.toString());

      // ! se usa para los contadores
      var responseValues = await Connections().getValuesTrasporter(populate, [
        {
          "transportadora": {"\$not": null}
        },
        {
          'transportadora': {
            'id': sharedPrefs!.getString("idTransportadora").toString()
          }
        }
      ]);
      var responseCounters =
          await Connections().getOrdersDashboardTransportadora(populate, [
        {
          "transportadora": {"\$not": null}
        },
        {
          'transportadora': {
            'id': sharedPrefs!.getString("idTransportadora").toString()
          }
        }
      ]);
      valuesTransporter = responseValues;

      setState(() {
        data = [];
        data = response['data'];

        total = response['total'];

        pageCount = response['last_page'];
        // paginateData();
        // ! se usa para los contadores
        dataCounters = responseCounters;

        paginatorController.navigateToPage(0);

        // _scrollController.jumpTo(0);
      });

      // ! AQUI SE ACTUALIZAN LOS VARLORES
      updateCounters();
      calculateValues();

      Future.delayed(const Duration(milliseconds: 500), () {
        Navigator.pop(context);
      });
      setState(() {
        isFirst = false;
        isLoading = false;
      });
    } catch (e) {
      setState(() {
        isLoading = false;
      });
      Navigator.pop(context);

      // _showErrorSnackBar(context, "Ha ocurrido un error de conexión");
    }
  }


  initializeDates() {
    if (sharedPrefs!.getString("dateDesdeTransportadora") == null) {
      sharedPrefs!.setString("dateDesdeTransportadora",
          "${DateTime.now().day}/${DateTime.now().month}/${DateTime.now().year}");
    }
    _controllers.startDateController.text =
        sharedPrefs!.getString("dateDesdeTransportadora")!;

    if (sharedPrefs!.getString("dateHastaTransportadora") == null) {
      sharedPrefs!.setString("dateHastaTransportadora", "1/1/2200");
    }
    _controllers.endDateController.text =
        sharedPrefs!.getString("dateHastaTransportadora") != "1/1/2200"
            ? sharedPrefs!.getString("dateHastaTransportadora")!
            : "";
  }

  paginateData() async {
    try {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        getLoadingModal(context, false);
      });

      setState(() {
        search = false;
      });

      var response = await Connections()
          .getOrdersForSellerStateSearchForDateTransporterLaravel(
              populate,
              arrayFiltersAnd,
              arrayFiltersOr,
              currentPage,
              pageSize,
              _controllers.searchController.text,
              sortField.toString());

      setState(() {
        data = [];
        data = response['data'];

        // pageCount = updateTotalPages(response['meta']['total'], pageSize);
      });

      Future.delayed(Duration(milliseconds: 500), () {
        Navigator.pop(context);
      });
      // setState(() {
      //   isFirst = false;

      //   // isLoading = false;
      // });
    } catch (e) {
      Navigator.pop(context);

      _showErrorSnackBar(context, "Ha ocurrido un error de conexión");
    }
  }

  void _showErrorSnackBar(BuildContext context, String errorMessage) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          errorMessage,
          style: TextStyle(color: Color.fromRGBO(7, 0, 0, 1)),
        ),
        backgroundColor: Color.fromARGB(255, 253, 101, 90),
        duration: Duration(seconds: 4),
      ),
    );
  }

  // int updateTotalPages(int totalRegistros, int registrosPorPagina) {
  //   final int totalPaginas = totalRegistros ~/ registrosPorPagina;
  //   final int registrosRestantes = totalRegistros % registrosPorPagina;

  //   return registrosRestantes > 0
  //       ? totalPaginas + 1
  //       : totalPaginas == 0
  //           ? 1
  //           : totalPaginas;
  // }

  // String getCurrentDate() {
  //   DateTime now = DateTime.now();
  //   String formattedDate =
  //       "${now.day}/${now.month}/20${now.year.toString().substring(2)}";

  //   return formattedDate;
  // }

  @override
  Widget build(BuildContext context) {
    // String operatorVal = transporterOperator;
    List<Opcion> options = [
      Opcion(
          icono: const Icon(Icons.route),
          titulo: 'En Ruta',
          filtro: 'En Ruta',
          valor: enRuta,
          color: const Color.fromARGB(255, 33, 150, 243)),
      Opcion(
          icono: const Icon(Icons.send),
          titulo: 'Entregado',
          filtro: 'Entregado',
          valor: entregados,
          color: const Color.fromARGB(255, 102, 187, 106)),
      Opcion(
          icono: const Icon(Icons.schedule),
          titulo: 'Reagendado',
          filtro: 'Reagendado',
          valor: reagendados,
          color: const Color.fromARGB(255, 227, 32, 241)),
      Opcion(
          icono: const Icon(Icons.lock_clock),
          titulo: 'Programado',
          filtro: 'Pedido Programado',
          valor: programado,
          color: const Color.fromARGB(255, 239, 127, 14)),
      Opcion(
          icono: const Icon(Icons.error),
          titulo: 'No Entregado',
          filtro: 'No Entregado',
          valor: noEntregados,
          color: const Color.fromARGB(255, 243, 33, 33)),
      Opcion(
          icono: const Icon(Icons.campaign),
          titulo: 'Novedad',
          filtro: 'Novedad',
          valor: conNovedad,
          color: const Color.fromARGB(255, 244, 225, 57)),
      Opcion(
          icono: const Icon(Icons.all_inbox),
          titulo: 'Total',
          filtro: 'Total',
          valor: total,
          color: const Color.fromARGB(255, 108, 108, 109)),
    ];

    Column InputFilter(String title, filter, var controller) {
      return Column(
        children: [
          Text(title),
          Expanded(
              child: Container(
            margin: const EdgeInsets.only(bottom: 4),
            // margin: const EdgeInsets.only(left: 4, right: 8, top: 4, bottom: 4),
            child: TextField(
              style: const TextStyle(fontSize: 13.0),
              controller: controller,
              maxLines: 1,
              scrollPhysics: const NeverScrollableScrollPhysics(),
              onChanged: (value) {
                // ! hace lo mismo solo dejar una ↓
                // if (value != '') {
                // arrayFiltersAnd
                // .removeWhere((element) => element.containsKey(filter));

                // }else{
                arrayFiltersAnd
                    .removeWhere((element) => element.containsKey(filter));
                // }
              },
              onSubmitted: (value) {
                if (value != '') {
                  // arrayFiltersAnd.clear();
                  arrayFiltersAnd.add({filter: value});
                } else {
                  arrayFiltersAnd
                      .removeWhere((element) => element.containsKey(filter));
                }
                // }

                loadData(context);
              },
              decoration: InputDecoration(
                  border: OutlineInputBorder(
                      borderRadius: const BorderRadius.all(Radius.circular(5)),
                      borderSide: BorderSide(
                          color: controller.text.toString() != ""
                              ? Colors.green
                              : Colors.grey))),
            ),
          ))
        ],
      );
    }

    return Scaffold(
        body: Container(
      width: double.infinity,
      padding: const EdgeInsets.only(left: 10, right: 10, bottom: 10),
      color: Colors.grey[100],
      child: Column(children: [
        Container(
          width: double.infinity,
          color: Colors.white,
          child: responsive(
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  boxValuesTransport(
                    totalValoresRecibidos: totalValoresRecibidos,
                    costoDeEntregas: costoTransportadora,
                  ),
                ],
              ),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  boxValuesTransport(
                    totalValoresRecibidos: totalValoresRecibidos,
                    costoDeEntregas: costoTransportadora,
                  ),
                ],
              ),
              context),
        ),
        Container(
          width: double.infinity,
          color: Colors.white,
          child: responsive(
              Row(
                children: [
                  Expanded(
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          padding: const EdgeInsets.only(left: 15, right: 5),
                          child: responsive(
                              Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: fechaFinFechaIni(),
                              ),
                              Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: fechaFinFechaIni(),
                              ),
                              context),
                        ),
                      ],
                    ),
                  ),
                  // boxValuesTransport(
                  //   totalValoresRecibidos: totalValoresRecibidos,
                  //   costoDeEntregas: costoTransportadora,
                  // ),
                ],
              ),
              Row(
                children: [
                  Expanded(
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          padding: const EdgeInsets.only(left: 15, right: 5),
                          child: responsive(
                              Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: fechaFinFechaIni(),
                              ),
                              Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: fechaFinFechaIni(),
                              ),
                              context),
                        ),
                      ],
                    ),
                  ),
                  // boxValuesTransport(
                  //   totalValoresRecibidos: totalValoresRecibidos,
                  //   costoDeEntregas: costoTransportadora,
                  // ),
                ],
              ),
              context),
        ),
        responsive(
            Container(
                height: MediaQuery.of(context).size.height * 0.10,
                child: OptionsWidget(
                    function: addFilter,
                    options: options,
                    currentValue: currentValue)),
            Container(
                height: MediaQuery.of(context).size.height * 0.10,
                child: OptionsWidget(
                    function: addFilter,
                    options: options,
                    currentValue: currentValue)),
            context),
        Container(
          color: currentColor.withOpacity(0.3),
          child: responsive(
              Row(
                children: [
                  Expanded(
                    child: _modelTextField(
                        text: "Buscar",
                        controller: _controllers.searchController),
                  ),
                  Container(
                    padding: const EdgeInsets.only(left: 45, right: 15),
                    child: ElevatedButton(
                        style: const ButtonStyle(
                            backgroundColor: MaterialStatePropertyAll(
                                Color.fromARGB(255, 67, 67, 67))),
                        onPressed: () async {
                          await showDialog(
                              context: context,
                              builder: (context) {
                                return ScannerDeliveryStatusTransport(
                                  function: OpenScannerInfo,
                                );
                              });
                          await loadData(context);
                        },
                        child: const Text(
                          "SCANNER",
                          style: TextStyle(fontWeight: FontWeight.bold),
                        )),
                  ),
                  Expanded(child: numberPaginator()),
                ],
              ),
              Column(
                children: [
                  Container(
                    child: _modelTextField(
                        text: "Buscar",
                        controller: _controllers.searchController),
                  ),
                  Container(
                    padding: const EdgeInsets.only(left: 45, right: 15),
                    child: ElevatedButton(
                        onPressed: () async {
                          await showDialog(
                              context: context,
                              builder: (context) {
                                return ScannerDeliveryStatusTransport(
                                  function: OpenScannerInfo,
                                );
                              });
                          await loadData(context);
                        },
                        child: const Text(
                          "SCANNER",
                          style: TextStyle(fontWeight: FontWeight.bold),
                        )),
                  ),
                  numberPaginator()
                ],
              ),
              context),
        ),
        Expanded(
            child: DataTableModelPrincipal(
                columns: getColumns(InputFilter), rows: buildDataRows(data))),
      ]),
    ));
  }

  // ! LISTAS DE FILAS Y COLUMNAS PARA LA TABLA

  List<DataRow> buildDataRows(List data) {
    List<DataRow> rows = [];

    for (int index = 0; index < data.length; index++) {
      DataRow row = DataRow(
        cells: [
          DataCell(InkWell(
              child: Text(
                  data[index]['marca_tiempo_envio'].toString().split(" ")[0]),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['fecha_entrega'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(
                  style: TextStyle(
                      color: GetColor(data[index]['status'].toString())!),
                  '${data[index]['nombre_comercial'].toString()}-${data[index]['numero_orden'].toString()}'),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['nombre_shipping'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['ciudad_shipping'] != null &&
                      data[index]['ciudad_shipping'].isNotEmpty
                  ? data[index]['ciudad_shipping'].toString()
                  : ""),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['direccion_shipping'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['telefono_shipping'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['cantidad_total'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['productop'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['producto_extra'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['precio_total'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['observacion'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['comentario'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(
                  style: TextStyle(
                      color: GetColor(data[index]['status'].toString())!),
                  data[index]['status'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['tipo_pago'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(
              InkWell(
                  child: Text(data[index]['sub_ruta'] != null &&
                          data[index]['sub_ruta'].isNotEmpty
                      ? data[index]['sub_ruta'][0]['titulo'].toString()
                      : "")), onTap: () {
            OpenShowDialog(context, index);
          }),
          DataCell(
              InkWell(
                child: Text(data[index]['operadore'].toString() != null &&
                        data[index]['operadore'].toString().isNotEmpty
                    ? data[index]['operadore'] != null &&
                            data[index]['operadore'].isNotEmpty &&
                            data[index]['operadore'][0]['up_users'] != null &&
                            data[index]['operadore'][0]['up_users']
                                .isNotEmpty &&
                            data[index]['operadore'][0]['up_users'][0]
                                    ['username'] !=
                                null
                        ? data[index]['operadore'][0]['up_users'][0]['username']
                        : ""
                    : ""),
              ), onTap: () {
            OpenShowDialog(context, index);
          }),
          DataCell(InkWell(
              child: Text(data[index]['estado_devolucion'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['marca_t_d'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['marca_t_d_l'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['marca_t_d_t'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['marca_t_i'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: Text(data[index]['estado_pagado'].toString()),
              onTap: () {
                OpenShowDialog(context, index);
              })),
          DataCell(InkWell(
              child: getLengthArrayMap(data[index]['novedades']),
              onTap: () {
                OpenShowDialog(context, index);
              })),
        ],
        // onSelectChanged: (isSelected) {
        // },
      );
      rows.add(row);
    }

    return rows;
  }

  List<DataColumn2> getColumns(InputFilter) {
    return [
      DataColumn2(
        label: InputFilter(
            'Fecha', 'marca_tiempo_envio', _controllers.fechaController),
        size: ColumnSize.L,
        onSort: (columnIndex, ascending) {
          sortFunc("marca_tiempo_envio");
        },
      ),
      DataColumn2(
        label: InputFilter('Fecha de Entrega', 'fecha_entrega',
            _controllers.fechaentregaController),
        size: ColumnSize.L,
        onSort: (columnIndex, ascending) {
          sortFunc("fecha_entrega");
        },
      ),
      DataColumn2(
        label: InputFilter(
            'Código', 'numero_orden', _controllers.codigoController),
        size: ColumnSize.L,
        onSort: (columnIndex, ascending) {
          sortFunc("numero_orden");
        },
      ),
      DataColumn2(
        label: InputFilter('Nombre Cliente', 'nombre_shipping',
            _controllers.nombreClienteController),
        size: ColumnSize.L,
        onSort: (columnIndex, ascending) {
          sortFunc("nombre_shipping");
        },
      ),
      DataColumn2(
        label: InputFilter(
            'Ciudad', 'ciudad_shipping', _controllers.ciudadClienteController),
        size: ColumnSize.L,
        onSort: (columnIndex, ascending) {
          sortFunc("ciudad_shipping");
        },
      ),
      DataColumn2(
        label: InputFilter('Dirección', 'direccion_shipping',
            _controllers.direccionClienteController),
        size: ColumnSize.L,
        onSort: (columnIndex, ascending) {
          sortFunc("direccion_shipping");
        },
      ),
      DataColumn2(
        label: InputFilter('Teléfono Cliente', 'telefono_shipping',
            _controllers.telefonoClienteController),
        size: ColumnSize.L,
        onSort: (columnIndex, ascending) {
          sortFunc("telefono_shipping");
        },
      ),
      DataColumn2(
        label: InputFilter(
            'Cantidad', 'cantidad_total', _controllers.cantidadController),
        size: ColumnSize.M,
        numeric: true,
        onSort: (columnIndex, ascending) {
          sortFunc("cantidad_total");
        },
      ),
      DataColumn2(
        label: InputFilter(
            'Producto', 'producto_p', _controllers.productoController),
        size: ColumnSize.M,
        onSort: (columnIndex, ascending) {
          sortFunc("producto_p");
        },
      ),
      DataColumn2(
        label: InputFilter('Producto Extra', 'producto_extra',
            _controllers.productoextraController),
        size: ColumnSize.M,
        onSort: (columnIndex, ascending) {
          sortFunc("producto_extra");
        },
      ),
      DataColumn2(
        label: InputFilter(
            'Precio Total', 'precio_total', _controllers.precioTotalController),
        size: ColumnSize.M,
        numeric: true,
        onSort: (columnIndex, ascending) {
          sortFunc("precio_total");
        },
      ),
      DataColumn2(
        label: InputFilter(
            'Observación', 'observacion', _controllers.observacionController),
        size: ColumnSize.M,
        numeric: true,
        onSort: (columnIndex, ascending) {
          sortFunc("observacion");
        },
      ),
      DataColumn2(
        label: InputFilter(
            'Comentario', 'comentario', _controllers.comentarioController),
        size: ColumnSize.M,
        numeric: true,
        onSort: (columnIndex, ascending) {
          sortFunc("comentario");
        },
      ),
      DataColumn2(
        label: InputFilter('Status', 'status', _controllers.statusController),
        size: ColumnSize.M,
        onSort: (columnIndex, ascending) {
          sortFunc("status");
        },
      ),
      DataColumn2(
        label: InputFilter(
          'Tipo de Pago',
          'tipo_pago',
          _controllers.tipoPagoController,
        ),
        size: ColumnSize.M,
        onSort: (columnIndex, ascending) {
          sortFunc("tipo_pago");
        },
      ),
      DataColumn2(
        label: InputFilter(
          'Sub Ruta',
          'subRuta.titulo',
          _controllers.suRutaController,
        ),
        // label: const Text('Sub Ruta'),
        size: ColumnSize.M,
        onSort: (columnIndex, ascending) {
          // ! no funciona ↓
          // sortFunc("subRuta.titulo");
        },
      ),
      DataColumn2(
        label: SelectFilter('Operador', 'operadore.up_users.username',
            operadorController, listOperators),
        size: ColumnSize.M,
        onSort: (columnIndex, ascending) {
          // sortFuncOperator();
        },
      ),
      DataColumn2(
        label: InputFilter(
            'Est. Dev', 'estado_devolucion', _controllers.estDevController),
        size: ColumnSize.M,
        onSort: (columnIndex, ascending) {
          sortFunc("estado_devolucion");
        },
      ),
      DataColumn2(
        label:
            InputFilter('MDT. OF.', 'marca_t_d', _controllers.mdtOfController),
        size: ColumnSize.M,
        onSort: (columnIndex, ascending) {
          sortFunc("marca_t_d");
        },
      ),
      DataColumn2(
        label: InputFilter(
            'MDT. BOD. ', 'marca_t_d_l', _controllers.mdtBodController),
        size: ColumnSize.M,
        onSort: (columnIndex, ascending) {
          sortFunc("marca_t_d_l");
        },
      ),
      DataColumn2(
        label: InputFilter(
            'MDT. RUTA', 'marca_t_d_t', _controllers.mdtRutaController),
        size: ColumnSize.M,
        onSort: (columnIndex, ascending) {
          sortFunc("marca_t_d_t");
        },
      ),
      DataColumn2(
        label:
            InputFilter('MTD. INP', 'marca_t_i', _controllers.mtdInpController),
        size: ColumnSize.M,
        onSort: (columnIndex, ascending) {
          sortFunc("marca_t_i");
        },
      ),
      DataColumn2(
        label: InputFilter(
            'Estado de Pago', 'estado_pago', _controllers.estadoPagoController),
        size: ColumnSize.M,
        onSort: (columnIndex, ascending) {
          sortFunc("estado_pago");
        },
      ),
      DataColumn2(
        label: const Text("N. intentos"),
        // label: InputFilter(
        // 'N. intentos', 'novedades', nIntentosController),
        size: ColumnSize.M,
        onSort: (columnIndex, ascending) {},
      ),
    ];
  }

  getLengthArrayMap(List data) {
    var arraylength = data.length;
    return Text(
      arraylength.toString(),
      style: TextStyle(
          color: arraylength > 3
              ? const Color.fromARGB(255, 54, 244, 73)
              : Colors.black),
    );
  }

  Future<dynamic> OpenShowDialog(BuildContext context, int index) {
    return showDialog(
        context: context,
        builder: (context) {
          return AlertDialog(
            content: Container(
              width: MediaQuery.of(context).size.width,
              height: MediaQuery.of(context).size.height,
              child: Column(
                children: [
                  Align(
                    alignment: Alignment.centerRight,
                    child: GestureDetector(
                      onTap: () {
                        Navigator.pop(context);
                      },
                      child: Icon(Icons.close),
                    ),
                  ),
                  Expanded(
                      child: TransportProDeliveryHistoryDetails(
                    id: data[index]['id'].toString(),
                  ))
                ],
              ),
            ),
          );
        });
  }

  Future<dynamic> OpenScannerShowDialog(BuildContext context, String id) {
    return showDialog(
        context: context,
        builder: (context) {
          return AlertDialog(
            content: Container(
              width: MediaQuery.of(context).size.width,
              height: MediaQuery.of(context).size.height,
              child: Column(
                children: [
                  Align(
                    alignment: Alignment.centerRight,
                    child: GestureDetector(
                      onTap: () {
                        Navigator.pop(context);
                      },
                      child: Icon(Icons.close),
                    ),
                  ),
                  Expanded(
                      child: TransportProDeliveryHistoryDetails(
                    id: id,
                  ))
                ],
              ),
            ),
          );
        });
  }

  _modelTextField({text, controller}) {
    return Container(
      width: double.infinity,
      margin: EdgeInsets.all(3),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10.0),
        color: Color.fromARGB(255, 245, 244, 244),
      ),
      child: TextField(
        controller: controller,
        onSubmitted: (value) {
          // ! aqui se debe agregar el arrayFiltersAnd.add
          // sharedPrefs!.setString("dateDesdeTransportadora", '1/1/2000');
          // sharedPrefs!.setString("dateHastaTransportadora", '1/1/2200');
          // _controllers.startDateController.text = "";
          // _controllers.endDateController.text = "";
          loadData(context);
        },
        onChanged: (value) {
          setState(() {});
        },
        style: TextStyle(fontWeight: FontWeight.bold),
        decoration: InputDecoration(
          fillColor: Colors.grey[500],
          prefixIcon: Icon(Icons.search),
          suffixIcon: _controllers.searchController.text.isNotEmpty
              ? GestureDetector(
                  onTap: () {
                    getLoadingModal(context, false);
                    setState(() {
                      _controllers.searchController.clear();
                    });

                    setState(() {
                      loadData(context);
                    });
                    Navigator.pop(context);
                  },
                  child: Icon(Icons.close))
              : null,
          hintText: text,
          border: const OutlineInputBorder(
            borderSide: BorderSide(color: Colors.grey),
          ),
          focusColor: Colors.black,
          iconColor: Colors.black,
        ),
      ),
    );
  }

  OpenScannerInfo(value) {
    var m = value;
    OpenScannerShowDialog(context, value);
  }

  sortFunc(filtro) {
    setState(() {
      // final direction = isRightClick ? "ASC" : "DESC";
      sortField = "$filtro:DESC";
      // print(sortField);
      loadData(context);
    });
  }

  sortFuncOperator() {
    if (sort) {
      setState(() {
        sort = false;
      });
      data.sort((a, b) => b['operadore']['data']['user']['data']['username']
          .toString()
          .compareTo(
              a['operadore']['data']['user']['data']['username'].toString()));
    } else {
      setState(() {
        sort = true;
      });
      data.sort((a, b) => a['operadore']['data']['user']['data']['username']
          .toString()
          .compareTo(
              b['operadore']['data']['user']['data']['username'].toString()));
    }
  }


  fechaFinFechaIni() {
    return [
      Row(
        children: [
          Text(_controllers.startDateController.text),
          IconButton(
            icon: const Icon(Icons.calendar_month),
            onPressed: () async {
              _controllers.startDateController.text = await OpenCalendar();
              sharedPrefs!.setString("dateDesdeTransportadora",
                  _controllers.startDateController.text);
            },
          ),
          const Text(' - '),
          Text(_controllers.endDateController.text),
          IconButton(
            icon: Icon(Icons.calendar_month),
            onPressed: () async {
              _controllers.endDateController.text = await OpenCalendar();
              sharedPrefs!.setString("dateHastaTransportadora",
                  _controllers.endDateController.text);
            },
          ),
          Row(
            children: [
              Padding(
                padding: const EdgeInsets.only(
                    right: 8.0), // Agrega el espaciado izquierdo
                child: ElevatedButton(
                  style: ButtonStyle(
                    backgroundColor: MaterialStateProperty.all(
                      Color.fromARGB(255, 67, 67, 67),
                    ),
                  ),
                  onPressed: () async {
                    // await applyDateFilter();
                    loadData(context);
                  },
                  child: Text('Filtrar'),
                ),
              ),
              ElevatedButton(
                style: ButtonStyle(
                  backgroundColor: MaterialStateProperty.all(
                    Color.fromARGB(255, 167, 7, 7),
                  ),
                ),
                onPressed: () async {
                  // await applyDateFilter();
                  setState(() {
                    limpiar();
                    loadData(context);
                  });
                },
                child: const Row(
                  // Usar un Row para combinar el icono y el texto
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.filter_alt), // Agregar el icono de filtro aquí
                    SizedBox(width: 8), // Espacio entre el icono y el texto
                    Text('Quitar Filtros'),
                  ],
                ),
              ),
            ],
          )
        ],
      ),
    ];
  }

  void limpiar() {
    setState(() {
      sortField = "";
    });
    arrayFiltersAnd.clear();
    _controllers.searchController.clear();
    _controllers.fechaController.clear();
    _controllers.fechaentregaController.clear();
    _controllers.codigoController.clear();
    _controllers.nombreClienteController.clear();
    _controllers.ciudadClienteController.clear();
    _controllers.direccionClienteController.clear();
    _controllers.telefonoClienteController.clear();
    _controllers.cantidadController.clear();
    _controllers.productoController.clear();
    _controllers.productoextraController.clear();
    _controllers.precioTotalController.clear();
    _controllers.observacionController.clear();
    _controllers.comentarioController.clear();
    _controllers.statusController.clear();
    _controllers.tipoPagoController.clear();
    _controllers.suRutaController.clear();
    _controllers.estDevController.clear();
    _controllers.mdtOfController.clear();
    _controllers.mdtBodController.clear();
    _controllers.mdtRutaController.clear();
    _controllers.mtdInpController.clear();
    _controllers.estadoPagoController.clear();
    // nIntentosController.clear();
  }

  Future<String> OpenCalendar() async {
    String nuevaFecha = "";

    var results = await showCalendarDatePicker2Dialog(
      context: context,
      config: CalendarDatePicker2WithActionButtonsConfig(
        dayTextStyle: TextStyle(fontWeight: FontWeight.bold),
        yearTextStyle: TextStyle(fontWeight: FontWeight.bold),
        selectedYearTextStyle: TextStyle(fontWeight: FontWeight.bold),
        weekdayLabelTextStyle: TextStyle(fontWeight: FontWeight.bold),
      ),
      dialogSize: const Size(325, 400),
      borderRadius: BorderRadius.circular(15),
    );

    setState(() {
      if (results != null) {
        String fechaOriginal = results![0]
            .toString()
            .split(" ")[0]
            .split('-')
            .reversed
            .join('-')
            .replaceAll("-", "/");
        List<String> componentes = fechaOriginal.split('/');

        String dia = int.parse(componentes[0]).toString();
        String mes = int.parse(componentes[1]).toString();
        String anio = componentes[2];

        nuevaFecha = "$dia/$mes/$anio";

        // sharedPrefs!.setString("dateDesdeTransporte", nuevaFecha);
      }
    });

    return nuevaFecha;
  }

  updateCounters() {
    entregados = 0;
    noEntregados = 0;
    conNovedad = 0;
    reagendados = 0;
    enRuta = 0;
    programado = 0;
    setState(() {
      entregados = int.parse(dataCounters['ENTREGADO'].toString()) ?? 0;
      noEntregados = int.parse(dataCounters['NO ENTREGADO'].toString()) ?? 0;
      conNovedad = int.parse(dataCounters['NOVEDAD'].toString()) ?? 0;
      reagendados = int.parse(dataCounters['REAGENDADO'].toString()) ?? 0;
      enRuta = int.parse(dataCounters['EN RUTA'].toString()) ?? 0;
      // programado = int.parse(data['EN OFICINA'].toString()) ?? 0;
      programado = int.parse(dataCounters['PEDIDO PROGRAMADO'].toString()) ?? 0;
    });
  }

  Color? GetColor(state) {
    int color = 0xFF000000;

    switch (state) {
      case "ENTREGADO":
        color = 0xFF33FF6D;
        break;
      case "NOVEDAD":
        color = 0xFFD6DC27;
        break;
      case "NO ENTREGADO":
        color = 0xFFFF3333;
        break;
      case "REAGENDADO":
        color = 0xFFFA37BF;
        break;
      case "EN RUTA":
        color = 0xFF3341FF;
        break;
      case "EN OFICINA":
        color = 0xFF4B4C4B;
        break;

      default:
        color = 0xFF000000;
    }

    return Color(color);
  }

  calculateValues() {
    setState(() {
      totalValoresRecibidos =
          double.parse(valuesTransporter['totalValoresRecibido'].toString());
      costoTransportadora =
          double.parse(valuesTransporter['costoTransportadora'].toString());
    });
  }

  Column SelectFilter(String title, filter, TextEditingController controller,
      List<String> listOptions) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title),
        Expanded(
          child: Container(
            margin: EdgeInsets.only(bottom: 4.5, top: 4.5),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(5.0),
              border: Border.all(color: Color.fromRGBO(6, 6, 6, 1)),
            ),
            height: 50,
            child: DropdownButtonFormField<String>(
              isExpanded: true,
              value: controller.text,
              onChanged: (String? newValue) {
                setState(() {
                  controller.text = newValue ?? "";

                  arrayFiltersAnd
                      .removeWhere((element) => element.containsKey(filter));

                  if (newValue != 'TODO') {
                    // reemplazarValor(value, newValue!);
                    //  print(value);

                    arrayFiltersAnd
                        .add({'operadore.up_users.username': newValue});
                  }

                  loadData(context);
                });
              },
              decoration: InputDecoration(
                  border: UnderlineInputBorder(
                      borderRadius: BorderRadius.circular(10))),
              items: listOptions.map<DropdownMenuItem<String>>((String value) {
                return DropdownMenuItem<String>(
                  value: value,
                  child:
                      Text(value.split("-")[0], style: TextStyle(fontSize: 15)),
                );
              }).toList(),
            ),
          ),
        ),
      ],
    );
  }

  addFilter(value) {
    // arrayFiltersAndEq.removeWhere((element) => element.containsKey("Status"));
    arrayFiltersAnd.clear();
    if (value["filtro"] != "Total") {
      arrayFiltersAnd.add({"status": value["filtro"]});
    }

    setState(() {
      currentColor = value['color'];
    });
    paginateData();
    loadData(context);
  }

  NumberPaginator numberPaginator() {
    return NumberPaginator(
      config: NumberPaginatorUIConfig(
        buttonUnselectedForegroundColor: const Color.fromARGB(255, 67, 67, 67) ,
        buttonSelectedBackgroundColor: const Color.fromARGB(255, 67, 67, 67),
        buttonShape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(5), // Customize the button shape
        ),
      ),
      controller: paginatorController,
      numberPages: pageCount > 0 ? pageCount : 1,
      onPageChange: (index) async {
        setState(() {
          currentPage = index + 1;
        });
        if (!isLoading) {
          await paginateData();
        }
      },
    );
  }

  sortFuncSubRoute() {
    if (sort) {
      setState(() {
        sort = false;
      });
      data.sort((a, b) => b['sub_ruta']['data']['Titulo']
          .toString()
          .compareTo(a['sub_ruta']['data']['titulo'].toString()));
    } else {
      setState(() {
        sort = true;
      });
      data.sort((a, b) => a['sub_ruta']['data']['Titulo']
          .toString()
          .compareTo(b['sub_ruta']['data']['titulo'].toString()));
    }
  }

//   void addFilterWithCurrentValue(String currentValue, dynamic filtro) {
//   addFilter(currentValue, filtro);
// }

//   addFilter(value,filtro) {
//     Map st = {"status":filtro};
//     arrayFiltersAnd.add(st);
//     // arrayFiltersAnd.removeWhere((element) => element.containsKey("status"!=value));
//     // if (value["filtro"] != "Total") {
//     //   arrayFiltersAndEq.add({"status": value});
//     // }

//     setState(() {
//       currentColor = value['color'];

//     });
//       paginateData();

//     loadData(context);
//   }
}
