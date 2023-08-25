import 'package:flutter/material.dart';

class BuildInfoContainer extends StatelessWidget {
  final String title;
  final String value;
  final bool isTitleOnTop;

  const BuildInfoContainer(
      {super.key,
      required this.title,
      required this.value,
      this.isTitleOnTop = false});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 85,
      padding: const EdgeInsets.only(top: 20),
      width: 250,
      decoration: BoxDecoration(
        border: Border.all(color: const Color(0xFF1A2B3C)),
        borderRadius: BorderRadius.circular(10),
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.grey.withOpacity(0.3),
            spreadRadius: 2,
            blurRadius: 5,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (isTitleOnTop)
            Align(
              alignment: Alignment.center,
              child: Text(
                title,
                style: TextStyle(
                  fontSize: 18,
                ),
              ),
            ),
           Align(
              alignment: Alignment.center,
              child:
          Text(
            value,
            style: const TextStyle(
              fontWeight: FontWeight.bold,
              fontSize: 30,
              color: Color.fromARGB(255, 22, 138, 232),
            ),
          ),),
          const SizedBox(height: 5),

          if (!isTitleOnTop)  Align(
              alignment: Alignment.center,
              child: Text(
                title,
                style: TextStyle(
                  fontSize: 18,
                ),
              ),
            ),

          // Text(
          //   title,
          //   style: TextStyle(
          //     fontWeight: FontWeight.bold,
          //     fontSize: 12,
          //     color: Colors.black,
          //   ),
          // ),
          // SizedBox(height: 5),
          // Text(
          //   value,
          //   style: TextStyle(
          //     fontWeight: FontWeight.bold,
          //     fontSize: 18,
          //     color: Colors.black,
          //   ),
          // ),
        ],
      ),
    );
  }
}
