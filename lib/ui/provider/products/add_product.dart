import 'package:awesome_dialog/awesome_dialog.dart';
import 'package:dropdown_button2/dropdown_button2.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animated_icons/icons8.dart';
import 'package:frontend/config/exports.dart';
import 'package:frontend/connections/connections.dart';
import 'package:frontend/helpers/server.dart';
import 'package:frontend/ui/logistic/transport_delivery_historial/show_error_snackbar.dart';
import 'package:frontend/ui/widgets/custom_succes_modal.dart';
import 'package:frontend/ui/widgets/loading.dart';
import 'package:image_picker/image_picker.dart';

class AddProduct extends StatefulWidget {
  const AddProduct({super.key});

  @override
  State<AddProduct> createState() => _AddProductState();
}

class _AddProductState extends State<AddProduct> {
  final formKey = GlobalKey<FormState>();

  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _priceController = TextEditingController();
  final TextEditingController _descriptionController = TextEditingController();
  final TextEditingController _stockController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _quantityController = TextEditingController();
  List<String> warehouses = [];
  // var warehouseList = [];
  List warehouseList = [];

  String? selectedWarehouse;
  List<String> categories = [];
  List<String> types = [];
  String? selectedType;
  String? selectedCat;
  List<String> selectedCategories = [];

  List<String> features = [];
  String? img_url;

  bool containsEmoji(String text) {
    final emojiPattern = RegExp(
        r'[\u2000-\u3300]|[\uD83C][\uDF00-\uDFFF]|[\uD83D][\uDC00-\uDE4F]'
        r'|[\uD83D][\uDE80-\uDEFF]|[\uD83E][\uDD00-\uDDFF]|[\uD83E][\uDE00-\uDEFF]');
    // r'|[!@#$%^&*()_+{}\[\]:;<>,.?~\\/-]');
    return emojiPattern.hasMatch(text);
  }

  @override
  void initState() {
    loadData();
    super.initState();
  }

  loadData() async {
    try {
      var responseBodegas = await Connections().getWarehouses();
      warehouseList = responseBodegas;
      if (warehouseList != null) {
        warehouseList.forEach((warehouse) {
          setState(() {
            warehouses.add(
                '${warehouse["branch_name"]}-${warehouse["warehouse_id"]}');
          });
        });
      }
      categories = [
        "Hogar",
        "Mascota",
        "Moda",
        "Tecnología",
        "Cocina",
        "Belleza"
      ];
//simple: b/n; variable: colores
      types = ["SIMPLE", "VARIABLE"];

      //
    } catch (e) {
      Navigator.pop(context);
      SnackBarHelper.showErrorSnackBar(
          context, "Ha ocurrido un error de conexión");
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: AppBar(
        title: const Text(
          "Añadir Nuevo Producto",
          style: TextStyle(
            fontWeight: FontWeight.bold,
            fontSize: 16,
          ),
        ),
        backgroundColor: Colors.blue[900],
        leading: Container(),
        centerTitle: true,
      ),
      content: Container(
        // decoration: BoxDecoration(
        //   border: Border.all(color: Colors.blue.shade900, width: 2.0),
        // ),
        // padding: const EdgeInsets.all(20.0),
        child: SizedBox(
          width: MediaQuery.of(context).size.width,
          height: MediaQuery.of(context).size.height,
          child: Form(
            key: formKey,
            child: ListView(
              children: [
                Column(
                  children: [
                    //
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Nombre del producto'),
                              const SizedBox(height: 3),
                              TextFormField(
                                controller: _nameController,
                                keyboardType: TextInputType.text,
                                maxLines: null,
                                decoration: InputDecoration(
                                  fillColor: Colors.white,
                                  // contentPadding: const EdgeInsets.symmetric(
                                  //     vertical: 10.0, horizontal: 15.0),
                                  filled: true,
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(5.0),
                                  ),
                                ),
                                validator: (value) {
                                  if (value!.isEmpty) {
                                    return 'Por favor, ingrese el nombre del producto';
                                  }
                                  return null;
                                },
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Precio'),
                              const SizedBox(height: 3),
                              TextFormField(
                                controller: _priceController,
                                keyboardType: TextInputType.number,
                                inputFormatters: <TextInputFormatter>[
                                  FilteringTextInputFormatter.allow(
                                      RegExp(r'^\d+\.?\d{0,2}$')),
                                ],
                                decoration: InputDecoration(
                                  fillColor: Colors.white,
                                  filled: true,
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(5.0),
                                  ),
                                ),
                                validator: (value) {
                                  if (value!.isEmpty) {
                                    return 'Por favor, ingrese el precio del producto';
                                  }
                                  return null;
                                },
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Tipo'),
                              const SizedBox(height: 3),
                              DropdownButtonHideUnderline(
                                child: DropdownButton2<String>(
                                  isExpanded: true,
                                  hint: Text(
                                    'Seleccione',
                                    style: TextStyle(
                                        fontSize: 14,
                                        color: Theme.of(context).hintColor,
                                        fontWeight: FontWeight.bold),
                                  ),
                                  items: types
                                      .map((item) => DropdownMenuItem(
                                            value: item,
                                            child: Text(
                                              item,
                                              style: const TextStyle(
                                                  fontSize: 14,
                                                  fontWeight: FontWeight.bold),
                                            ),
                                          ))
                                      .toList(),
                                  value: selectedType,
                                  onChanged: (value) async {
                                    setState(() {
                                      selectedType = value as String;
                                    });
                                  },
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 20),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Categoría'),
                              const SizedBox(height: 3),
                              DropdownButtonFormField<String>(
                                isExpanded: true,
                                hint: Text(
                                  'Seleccione',
                                  style: TextStyle(
                                    fontSize: 14,
                                    color: Theme.of(context).hintColor,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                items: categories
                                    .map((item) => DropdownMenuItem(
                                          value: item,
                                          child: Text(
                                            item,
                                            style: const TextStyle(
                                              fontSize: 14,
                                              fontWeight: FontWeight.bold,
                                            ),
                                          ),
                                        ))
                                    .toList(),
                                value: selectedCategories.isNotEmpty
                                    ? selectedCategories.first
                                    : null,
                                onChanged: (value) {
                                  setState(() {
                                    selectedCat = value;
                                    if (value != null) {
                                      selectedCategories.add(value);
                                    }
                                  });
                                },
                                decoration: InputDecoration(
                                  fillColor: Colors.white,
                                  filled: true,
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(5.0),
                                  ),
                                ),
                              ),
                              Wrap(
                                spacing: 8.0,
                                runSpacing: 8.0,
                                children:
                                    selectedCategories.map<Widget>((category) {
                                  return Chip(
                                    label: Text(category),
                                    onDeleted: () {
                                      setState(() {
                                        selectedCategories.remove(category);
                                        // print("catAct: $selectedCategories");
                                      });
                                    },
                                  );
                                }).toList(),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: SingleChildScrollView(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Descripción'),
                                const SizedBox(height: 3),
                                TextFormField(
                                  controller: _descriptionController,
                                  maxLines: null,
                                  decoration: InputDecoration(
                                    fillColor: Colors.white,
                                    filled: true,
                                    border: OutlineInputBorder(
                                      borderRadius: BorderRadius.circular(5.0),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Cantidad en Stock'),
                              const SizedBox(height: 3),
                              TextFormField(
                                controller: _stockController,
                                keyboardType: TextInputType.number,
                                inputFormatters: <TextInputFormatter>[
                                  FilteringTextInputFormatter.digitsOnly
                                ],
                                decoration: InputDecoration(
                                  fillColor: Colors.white,
                                  filled: true,
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(5.0),
                                  ),
                                ),
                                validator: (value) {
                                  if (value!.isEmpty) {
                                    return 'Por favor, ingrese la cantidad en stock del producto';
                                  }
                                  return null;
                                },
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 20),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Bodega:'),
                              const SizedBox(height: 3),
                              DropdownButtonHideUnderline(
                                child: DropdownButton2<String>(
                                  isExpanded: true,
                                  hint: Text(
                                    'Seleccione',
                                    style: TextStyle(
                                        fontSize: 14,
                                        color: Theme.of(context).hintColor,
                                        fontWeight: FontWeight.bold),
                                  ),
                                  items: warehouses
                                      .map((item) => DropdownMenuItem(
                                            value: item,
                                            child: Text(
                                              item.split('-')[0],
                                              style: const TextStyle(
                                                  fontSize: 14,
                                                  fontWeight: FontWeight.bold),
                                            ),
                                          ))
                                      .toList(),
                                  value: selectedWarehouse,
                                  onChanged: (value) async {
                                    setState(() {
                                      selectedWarehouse = value as String;
                                    });
                                  },
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Container(
                      margin: const EdgeInsets.symmetric(vertical: 10),
                      padding: const EdgeInsets.all(15),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(10),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.grey.withOpacity(0.3),
                            spreadRadius: 2,
                            blurRadius: 5,
                            offset: const Offset(0, 3),
                          ),
                        ],
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          ElevatedButton(
                            onPressed: () async {
                              final ImagePicker picker = ImagePicker();
                              final XFile? image = await picker.pickImage(
                                  source: ImageSource.gallery);

                              if (image != null && image.path.isNotEmpty) {
                                var responseI =
                                    await Connections().postDoc(image);
                                print("ImgSaveStrapi: $responseI");

                                setState(() {
                                  img_url = responseI[1];
                                });

                                // Navigator.pop(context);
                                // Navigator.pop(context);
                              } else {
                                print("No img");
                              }
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.blue[300],
                            ),
                            child: const Text(
                              "Agregar imagen",
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 12,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 15),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        // Mostrar la imagen si está disponible
                        img_url != null
                            ? SizedBox(
                                width: 300,
                                height: 400,
                                child: Image.network(
                                  "$generalServer$img_url",
                                  fit: BoxFit.fill,
                                ),
                              )
                            : Container(),
                      ],
                    ),
                    const SizedBox(height: 20),
                    const Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Productos Privados ',
                                style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                    color: Colors.black,
                                    fontSize: 14),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Correo Electrónico'),
                              const SizedBox(height: 3),
                              TextFormField(
                                controller: _emailController,
                                keyboardType: TextInputType.emailAddress,
                                decoration: InputDecoration(
                                  fillColor: Colors.white,
                                  filled: true,
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(5.0),
                                  ),
                                ),
                                onChanged: (email) {
                                  setState(() {});
                                },
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 20),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Cantidad'),
                              const SizedBox(height: 3),
                              TextFormField(
                                controller: _quantityController,
                                enabled: _emailController.text.isNotEmpty,
                                keyboardType: TextInputType.number,
                                inputFormatters: <TextInputFormatter>[
                                  FilteringTextInputFormatter.digitsOnly
                                ],
                                decoration: InputDecoration(
                                  fillColor: Colors.white,
                                  filled: true,
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(5.0),
                                  ),
                                ),
                                validator: (value) {
                                  if (_emailController.text.isNotEmpty &&
                                      value!.isEmpty) {
                                    return 'Por favor, ingresa la cantidad de los productos privados';
                                  }
                                  return null;
                                },
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    //btn
                    const SizedBox(height: 20),
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              ElevatedButton(
                                onPressed: () async {
                                  print("Guardar");
                                  if (formKey.currentState!.validate()) {
                                    getLoadingModal(context, false);

                                    if (selectedType == null ||
                                        selectedCategories.isEmpty ||
                                        selectedWarehouse == null) {
                                      AwesomeDialog(
                                        width: 500,
                                        context: context,
                                        dialogType: DialogType.error,
                                        animType: AnimType.rightSlide,
                                        title: 'Error de selección',
                                        desc:
                                            'Es necesario que seleccione el Tipo, Categoría/as y Bodega.',
                                        btnCancel: Container(),
                                        btnOkText: "Aceptar",
                                        btnOkColor: colors.colorGreen,
                                        btnCancelOnPress: () {},
                                        btnOkOnPress: () {},
                                      ).show();
                                      print(
                                          "Selecciona Tipo, Categoría y bodegaa");
                                    } else {
                                      if (_emailController.text.isNotEmpty) {
                                        if (!_emailController.text
                                            .contains('@')) {
                                          showSuccessModal(
                                              context,
                                              "Por favor, ingrese un correo electrónico válido.",
                                              Icons8.alert);
                                        } else {
                                          int? stock = int.tryParse(
                                              _stockController.text);
                                          int? cantidadPriv = int.tryParse(
                                              _quantityController.text);

                                          if ((cantidadPriv! > stock!) ||
                                              (cantidadPriv == 0)) {
                                            showSuccessModal(
                                                context,
                                                "Por favor, revise la cantidad de los productos privados.",
                                                Icons8.alert);
                                          }
                                        }
                                      }

                                      print(
                                          "Producto: ${_nameController.text}");
                                      print("Precio: ${_priceController.text}");
                                      print("Tipo: $selectedType");
                                      print("Categoria: $selectedCat");
                                      print(
                                          "Descripcion: ${_descriptionController.text}");
                                      print("Stock: ${_stockController.text}");
                                      print(
                                          "Bodega: ${selectedWarehouse.toString().split("-")[1].toString()}");
                                      print("Img: ");
                                      print("Email: ${_emailController.text}");
                                      print(
                                          "CantidadPriv: ${_quantityController.text}");

                                      var featuresToSend = [
                                        {
                                          "feature_name": "type",
                                          "value": selectedType
                                        },
                                        {
                                          "feature_name": "categories",
                                          "value": selectedCategories
                                        },
                                        {
                                          "feature_name": "description",
                                          "value": _descriptionController.text
                                        },
                                      ];
                                      await Connections().createProduct(
                                          _nameController.text,
                                          _stockController.text,
                                          featuresToSend,
                                          _priceController.text,
                                          img_url,
                                          selectedWarehouse
                                              .toString()
                                              .split("-")[1]
                                              .toString());

                                      Navigator.pop(context);
                                      Navigator.pop(context);
                                    }
                                  }
                                },
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.green[400],
                                ),
                                child: const Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(
                                      Icons.save_rounded,
                                      size: 24,
                                      color: Colors.white,
                                    ),
                                    Text(
                                      "Guardar",
                                      style: TextStyle(
                                          fontWeight: FontWeight.bold),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),

                    //
                  ],
                )
              ],
            ),
          ),
        ),
      ),
    );
  }
}