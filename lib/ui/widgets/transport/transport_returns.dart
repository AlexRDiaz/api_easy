import 'package:flutter/material.dart';
import 'package:flutter/src/widgets/framework.dart';
import 'package:flutter/src/widgets/placeholder.dart';
import 'package:frontend/connections/connections.dart';
import 'package:frontend/ui/widgets/loading.dart';

class TransportReturn extends StatefulWidget {
  final String id;
  final String status;
  const TransportReturn({super.key, required this.id, required this.status});

  @override
  State<TransportReturn> createState() => _TransportReturnState();
}

class _TransportReturnState extends State<TransportReturn> {
  bool entregado = false;
  bool ruta = false;
  bool reiniciar = false;
  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      content: Container(
        width: 400,
        height: MediaQuery.of(context).size.height,
        child: Center(
          child: ListView(
            children: [
              Text(
                "ESTADO",
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
              SizedBox(
                height: 10,
              ),
              widget.status == "DEVOLUCION EN RUTA"
                  ? Container()
                  : Row(
                      children: [
                        Checkbox(
                            value: entregado,
                            onChanged: (value) {
                              setState(() {
                                entregado = true;
                                ruta = false;
                                reiniciar = false;
                              });
                            }),
                        Flexible(
                          child: Text(
                            "ENTREGADO EN OFICINA",
                            style: TextStyle(
                                fontWeight: FontWeight.bold, fontSize: 13),
                          ),
                        )
                      ],
                    ),
              SizedBox(
                height: 10,
              ),
              Row(
                children: [
                  Checkbox(
                      value: ruta,
                      onChanged: (value) {
                        setState(() {
                          entregado = false;
                          ruta = true;
                          reiniciar = false;
                        });
                      }),
                  Flexible(
                    child: Text(
                      "DEVOLUCION EN RUTA",
                      style:
                          TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                  )
                ],
              ),
              SizedBox(
                height: 10,
              ),
              Row(
                children: [
                  Checkbox(
                      value: reiniciar,
                      onChanged: (value) {
                        setState(() {
                          entregado = false;
                          ruta = false;
                          reiniciar = true;
                        });
                      }),
                  Flexible(
                    child: Text(
                      "REINICIAR ESTADO",
                      style:
                          TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                  )
                ],
              ),
              SizedBox(
                height: 30,
              ),
              Wrap(
                children: [
                  TextButton(
                      onPressed: () {
                        Navigator.pop(context);
                      },
                      child: Text(
                        "CANCELAR",
                        style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: Colors.redAccent),
                      )),
                  SizedBox(
                    width: 10,
                  ),
                  TextButton(
                      onPressed: entregado == false &&
                              ruta == false &&
                              reiniciar == false
                          ? null
                          : () async {
                              getLoadingModal(context, false);
                              if (entregado) {
                                await Connections().updateOrderReturnTransport(
                                    widget.id,
                                    "ENTREGADO EN OFICINA",
                                    "Marca_T_D");
                                Navigator.pop(context);
                                Navigator.pop(context);
                              }
                              if (ruta) {
                                await Connections().updateOrderReturnTransport(
                                    widget.id,
                                    "DEVOLUCION EN RUTA",
                                    "Marca_T_D_T");

                                //debit by return here
                                var resTransaction = "";
                                var datacostos = await Connections()
                                    .getOrderByIDHistoryLaravel(widget.id);

                                if (datacostos['status'] == "NOVEDAD") {
                                  if (datacostos['estado_devolucion'] ==
                                          "ENTREGADO EN OFICINA" ||
                                      datacostos['estado_devolucion'] ==
                                          "DEVOLUCION EN RUTA" ||
                                      datacostos['estado_devolucion'] ==
                                          "EN BODEGA") {
                                    List existTransaction = await Connections()
                                        .getExistTransaction(
                                            "debit",
                                            "${datacostos["id"]}",
                                            "devolucion",
                                            datacostos['users'][0]['vendedores']
                                                [0]['id_master']);
                                    if (existTransaction.isEmpty) {
                                      var resDebit = await Connections().postDebit(
                                          "${datacostos['users'][0]['vendedores'][0]['id_master']}",
                                          "${datacostos['users'][0]['vendedores'][0]['costo_devolucion']}",
                                          "${datacostos['id']}",
                                          "${datacostos['name_comercial']}-${datacostos['numero_orden']}",
                                          "devolucion",
                                          "costo de devolucion de pedido desde transportadora por ${datacostos['estado_devolucion']}");
                                      await Connections()
                                          .updatenueva(widget.id, {
                                        "costo_devolucion": datacostos['users']
                                                [0]['vendedores'][0]
                                            ['costo_devolucion'],
                                      });
                                      if (resDebit != 1 && resDebit != 2) {
                                        resTransaction =
                                            "Pedido con novedad con costo devolucion";
                                      }
                                    }
                                  }
                                }

                                //
                                Navigator.pop(context);
                                Navigator.pop(context);
                              }
                              if (reiniciar) {
                                await Connections()
                                    .updateOrderReturnTransportRestart(
                                        widget.id);
                                Navigator.pop(context);
                                Navigator.pop(context);
                              }
                            },
                      child: Text(
                        "GUARDAR",
                        style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: Colors.blueAccent),
                      )),
                ],
              )
            ],
          ),
        ),
      ),
    );
  }
}
