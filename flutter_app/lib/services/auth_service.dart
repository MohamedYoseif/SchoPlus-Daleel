import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/school.dart';
import 'api_service.dart';

class AuthService {
  static const String _tokenKey = 'daleel_school_auth_token';
  static const String _schoolDataKey = 'daleel_school_data';
  static String? _cachedToken;

  static String? get token => _cachedToken;

  // Check if school is logged in
  static Future<bool> isLoggedIn() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }

  // Get current auth token
  static Future<String?> getToken() async {
    if (_cachedToken != null && _cachedToken!.isNotEmpty) return _cachedToken;
    final prefs = await SharedPreferences.getInstance();
    _cachedToken = prefs.getString(_tokenKey);
    return _cachedToken;
  }

  // Get saved school profile
  static Future<School?> getSavedSchool() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_schoolDataKey);
    if (raw == null || raw.isEmpty) return null;
    try {
      final json = jsonDecode(raw);
      return School.fromJson(json);
    } catch (_) {
      return null;
    }
  }

  // Login
  static Future<Map<String, dynamic>> login(String identifier, String password) async {
    final res = await ApiService.schoolLogin(identifier, password);
    final token = res['token'] as String;
    final schoolJson = res['school'] as Map<String, dynamic>;

    _cachedToken = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);
    await prefs.setString(_schoolDataKey, jsonEncode(schoolJson));

    return res;
  }

  // Save updated school locally
  static Future<void> saveSchoolLocally(School school) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_schoolDataKey, jsonEncode(school.toJson()));
  }

  // Logout
  static Future<void> logout() async {
    _cachedToken = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_schoolDataKey);
  }
}
