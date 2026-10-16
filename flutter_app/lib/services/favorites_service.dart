import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/school.dart';

class FavoritesService {
  static const String _key = 'saved_schools_daleel';
  static final ValueNotifier<List<School>> favoritesNotifier = ValueNotifier<List<School>>([]);

  static Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getStringList(_key) ?? [];
    final list = <School>[];
    for (final item in raw) {
      try {
        final map = jsonDecode(item);
        list.add(School.fromJson(map));
      } catch (_) {}
    }
    favoritesNotifier.value = list;
  }

  static bool isFavorite(int schoolId) {
    return favoritesNotifier.value.any((s) => s.id == schoolId);
  }

  static Future<bool> toggleFavorite(School school) async {
    final prefs = await SharedPreferences.getInstance();
    final currentList = List<School>.from(favoritesNotifier.value);
    final exists = currentList.any((s) => s.id == school.id);

    if (exists) {
      currentList.removeWhere((s) => s.id == school.id);
    } else {
      currentList.add(school);
    }

    final raw = currentList.map((s) => jsonEncode(s.toJson())).toList();
    await prefs.setStringList(_key, raw);
    favoritesNotifier.value = currentList;

    return !exists;
  }
}
