import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/home_data.dart';
import '../models/school.dart';
import '../models/news_item.dart';

class ApiService {
  static const String baseUrl = 'https://daleel.schoplus.sd/api/index.php';
  static final http.Client _client = http.Client();

  // 1. Fetch Home Screen Data (Stats, States, Featured, Top Rated, Latest, News)
  static Future<HomeData> getHome() async {
    final uri = Uri.parse('$baseUrl?action=home');
    final response = await _client.get(uri).timeout(const Duration(seconds: 15));

    if (response.statusCode == 200) {
      final json = jsonDecode(response.body);
      if (json['success'] == true && json['data'] != null) {
        return HomeData.fromJson(json['data']);
      }
      throw Exception(json['message'] ?? 'فشل في تحميل بيانات الصفحة الرئيسية');
    } else {
      throw Exception('خطأ في الاتصال بالخادم (${response.statusCode})');
    }
  }

  // 2. Fetch Schools with Search and Advanced Filters
  static Future<Map<String, dynamic>> getSchools({
    String? q,
    String? state,
    String? city,
    String? stage,
    String? type,
    String? curriculum,
    bool? schoplusOnly,
    int page = 1,
    int limit = 15,
  }) async {
    final params = <String, String>{
      'action': 'schools',
      'page': page.toString(),
      'limit': limit.toString(),
    };

    if (q != null && q.trim().isNotEmpty) params['q'] = q.trim();
    if (state != null && state.trim().isNotEmpty && state != 'الكل') params['state'] = state.trim();
    if (city != null && city.trim().isNotEmpty && city != 'الكل') params['city'] = city.trim();
    if (stage != null && stage.trim().isNotEmpty && stage != 'الكل') params['stage'] = stage.trim();
    if (type != null && type.trim().isNotEmpty && type != 'الكل') params['type'] = type.trim();
    if (curriculum != null && curriculum.trim().isNotEmpty && curriculum != 'الكل') params['curriculum'] = curriculum.trim();
    if (schoplusOnly == true) params['schoplus_only'] = '1';

    final uri = Uri.parse(baseUrl).replace(queryParameters: params);
    final response = await _client.get(uri).timeout(const Duration(seconds: 15));

    if (response.statusCode == 200) {
      final json = jsonDecode(response.body);
      if (json['success'] == true && json['data'] != null) {
        final data = json['data'];
        final schoolsList = (data['schools'] is List)
            ? (data['schools'] as List).map((e) => School.fromJson(e as Map<String, dynamic>)).toList()
            : <School>[];

        return {
          'total': data['total'] ?? 0,
          'page': data['page'] ?? 1,
          'totalPages': data['total_pages'] ?? 1,
          'schools': schoolsList,
        };
      }
      throw Exception(json['message'] ?? 'فشل في استرجاع نتائج المدارس');
    } else {
      throw Exception('خطأ في الاتصال بالخادم (${response.statusCode})');
    }
  }

  // 3. Fetch Single School Full Profile
  static Future<School> getSchoolDetails(int id) async {
    final uri = Uri.parse('$baseUrl?action=school&id=$id');
    final response = await _client.get(uri).timeout(const Duration(seconds: 15));

    if (response.statusCode == 200) {
      final json = jsonDecode(response.body);
      if (json['success'] == true && json['data'] != null) {
        return School.fromJson(json['data']);
      }
      throw Exception(json['message'] ?? 'المدرسة غير موجودة');
    } else {
      throw Exception('خطأ في الاتصال بالخادم (${response.statusCode})');
    }
  }

  // 4. Fetch News Stream
  static Future<Map<String, dynamic>> getNewsFeed({int page = 1, int limit = 15}) async {
    final uri = Uri.parse('$baseUrl?action=news&page=$page&limit=$limit');
    final response = await _client.get(uri).timeout(const Duration(seconds: 15));

    if (response.statusCode == 200) {
      final json = jsonDecode(response.body);
      if (json['success'] == true && json['data'] != null) {
        final data = json['data'];
        final newsList = (data['news'] is List)
            ? (data['news'] as List).map((e) => NewsItem.fromJson(e as Map<String, dynamic>)).toList()
            : <NewsItem>[];

        return {
          'total': data['total'] ?? 0,
          'page': data['page'] ?? 1,
          'totalPages': data['total_pages'] ?? 1,
          'news': newsList,
        };
      }
      throw Exception(json['message'] ?? 'فشل في تحميل الأخبار');
    } else {
      throw Exception('خطأ في الاتصال بالخادم (${response.statusCode})');
    }
  }

  // 5. Fetch Geographic & Category Meta (States with Cities, Stages, Types)
  static Future<Map<String, dynamic>> getMeta() async {
    final uri = Uri.parse('$baseUrl?action=meta');
    final response = await _client.get(uri).timeout(const Duration(seconds: 15));

    if (response.statusCode == 200) {
      final json = jsonDecode(response.body);
      if (json['success'] == true && json['data'] != null) {
        return json['data'];
      }
      throw Exception(json['message'] ?? 'فشل في تحميل بيانات المناطق');
    } else {
      throw Exception('خطأ في الاتصال بالخادم (${response.statusCode})');
    }
  }

  // 6. Submit In-App Admission / Inquiry Request
  static Future<Map<String, dynamic>> submitInquiry({
    required int schoolId,
    required String parentName,
    required String parentPhone,
    String? parentEmail,
    String? studentName,
    String? studentGender,
    String? studentStage,
    String? studentGrade,
    String? branchName,
    bool needsTransportation = false,
    String? message,
  }) async {
    final uri = Uri.parse('$baseUrl?action=inquiry');
    final payload = {
      'school_id': schoolId,
      'parent_name': parentName,
      'parent_phone': parentPhone,
      'parent_email': parentEmail,
      'student_name': studentName,
      'student_gender': studentGender,
      'student_stage': studentStage,
      'student_grade': studentGrade,
      'branch_name': branchName,
      'needs_transportation': needsTransportation ? 1 : 0,
      'message': message,
    };

    final response = await _client.post(
      uri,
      headers: {'Content-Type': 'application/json; charset=UTF-8'},
      body: jsonEncode(payload),
    ).timeout(const Duration(seconds: 15));

    final json = jsonDecode(response.body);
    if (response.statusCode == 200 && json['success'] == true) {
      return json;
    } else {
      throw Exception(json['message'] ?? 'فشل إرسال الطلب، يرجى المحاولة لاحقاً');
    }
  }
}
