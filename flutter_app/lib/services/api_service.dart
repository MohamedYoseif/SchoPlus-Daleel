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

  // 6. Check for In-App Updates (خاصية التحديثات)
  static Future<Map<String, dynamic>> checkUpdate({String currentVersion = '1.0.0', int versionCode = 1}) async {
    final uri = Uri.parse('$baseUrl?action=check_update&version=$currentVersion&version_code=$versionCode');
    final response = await _client.get(uri).timeout(const Duration(seconds: 10));

    if (response.statusCode == 200) {
      final json = jsonDecode(response.body);
      if (json['success'] == true) {
        return json;
      }
      throw Exception(json['message'] ?? 'فشل فحص التحديثات');
    } else {
      throw Exception('خطأ في الاتصال بالخادم');
    }
  }

  // 7. School Owner Login (تسجيل دخول مدراء المدارس)
  static Future<Map<String, dynamic>> schoolLogin(String identifier, String password) async {
    final uri = Uri.parse('$baseUrl?action=school_login');
    final response = await _client.post(
      uri,
      headers: {'Content-Type': 'application/json; charset=UTF-8'},
      body: jsonEncode({
        'identifier': identifier.trim(),
        'password': password,
      }),
    ).timeout(const Duration(seconds: 15));

    final json = jsonDecode(response.body);
    if (response.statusCode == 200 && json['success'] == true) {
      return json;
    } else {
      throw Exception(json['message'] ?? 'بيانات الدخول غير صحيحة');
    }
  }

  // 8. Fetch School Management Portal Data
  static Future<Map<String, dynamic>> getSchoolPortalData(String token) async {
    final uri = Uri.parse('$baseUrl?action=school_portal');
    final response = await _client.get(
      uri,
      headers: {
        'Content-Type': 'application/json; charset=UTF-8',
        'Authorization': 'Bearer $token',
      },
    ).timeout(const Duration(seconds: 15));

    final json = jsonDecode(response.body);
    if (response.statusCode == 200 && json['success'] == true) {
      return json;
    } else {
      throw Exception(json['message'] ?? 'فشل تحميل بيانات بوابة المدرسة');
    }
  }

  // 9. Update School Profile (تعديل كامل بيانات المدرسة)
  static Future<Map<String, dynamic>> updateSchoolProfile(String token, Map<String, dynamic> data) async {
    final uri = Uri.parse('$baseUrl?action=school_update_profile');
    final response = await _client.post(
      uri,
      headers: {
        'Content-Type': 'application/json; charset=UTF-8',
        'Authorization': 'Bearer $token',
      },
      body: jsonEncode(data),
    ).timeout(const Duration(seconds: 15));

    final json = jsonDecode(response.body);
    if (response.statusCode == 200 && json['success'] == true) {
      return json;
    } else {
      throw Exception(json['message'] ?? 'فشل حفظ وتحديث بيانات المدرسة');
    }
  }

  // 10. Update Inquiry Status (تحديث حالة طلب القبول)
  static Future<void> updateInquiryStatus(String token, int inquiryId, String status) async {
    final uri = Uri.parse('$baseUrl?action=school_inquiry_status');
    final response = await _client.post(
      uri,
      headers: {
        'Content-Type': 'application/json; charset=UTF-8',
        'Authorization': 'Bearer $token',
      },
      body: jsonEncode({
        'inquiry_id': inquiryId,
        'status': status,
      }),
    ).timeout(const Duration(seconds: 10));

    final json = jsonDecode(response.body);
    if (response.statusCode != 200 || json['success'] != true) {
      throw Exception(json['message'] ?? 'فشل تحديث حالة الطلب');
    }
  }

  // 11. Add News Item
  static Future<void> addSchoolNews(String token, String title, String content, String category, bool isPinned) async {
    final uri = Uri.parse('$baseUrl?action=school_add_news');
    final response = await _client.post(
      uri,
      headers: {
        'Content-Type': 'application/json; charset=UTF-8',
        'Authorization': 'Bearer $token',
      },
      body: jsonEncode({
        'title': title,
        'content': content,
        'category': category,
        'is_pinned': isPinned ? 1 : 0,
      }),
    ).timeout(const Duration(seconds: 10));

    final json = jsonDecode(response.body);
    if (response.statusCode != 200 || json['success'] != true) {
      throw Exception(json['message'] ?? 'فشل نشر الخبر');
    }
  }

  // 12. Delete News Item
  static Future<void> deleteSchoolNews(String token, int newsId) async {
    final uri = Uri.parse('$baseUrl?action=school_delete_news');
    final response = await _client.post(
      uri,
      headers: {
        'Content-Type': 'application/json; charset=UTF-8',
        'Authorization': 'Bearer $token',
      },
      body: jsonEncode({'news_id': newsId}),
    ).timeout(const Duration(seconds: 10));

    final json = jsonDecode(response.body);
    if (response.statusCode != 200 || json['success'] != true) {
      throw Exception(json['message'] ?? 'فشل حذف الخبر');
    }
  }
}

