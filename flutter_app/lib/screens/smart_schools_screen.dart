import 'package:flutter/material.dart';
import '../models/school.dart';
import '../theme/app_theme.dart';
import '../services/api_service.dart';
import '../widgets/school_card.dart';

class SmartSchoolsScreen extends StatefulWidget {
  const SmartSchoolsScreen({super.key});

  @override
  State<SmartSchoolsScreen> createState() => _SmartSchoolsScreenState();
}

class _SmartSchoolsScreenState extends State<SmartSchoolsScreen> {
  List<School> _schools = [];
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _fetch();
  }

  Future<void> _fetch() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final res = await ApiService.getSchools(schoplusOnly: true, limit: 50);
      if (mounted) {
        setState(() {
          _schools = res['schools'] as List<School>;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = e.toString().replaceAll('Exception: ', '');
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.bolt, color: AppTheme.gold, size: 20),
            SizedBox(width: 6),
            Text(
              'المدارس الذكية المعتمدة',
              style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
            ),
          ],
        ),
      ),
      body: RefreshIndicator(
        color: AppTheme.navy,
        onRefresh: _fetch,
        child: ListView(
          padding: const EdgeInsets.only(bottom: 24),
          children: [
            // Smart Schools Hero Explainer Card
            Container(
              margin: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [AppTheme.navy, AppTheme.blue],
                  begin: Alignment.topRight,
                  end: Alignment.bottomLeft,
                ),
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(
                    color: AppTheme.navy.withValues(alpha: 0.2),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(Icons.verified, color: AppTheme.gold, size: 24),
                      SizedBox(width: 8),
                      Text(
                        'منظومة المدارس الذكية',
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                          fontSize: 15,
                        ),
                      ),
                    ],
                  ),
                  SizedBox(height: 8),
                  Text(
                    'المدارس الحاصلة على شارة الشريك الذكي تتيح لأولياء الأمور متابعة الحضور والغياب، الدرجات، الواجبات والرسوم الدراسية عبر التطبيق والواتساب بشكل فوري ومباشر.',
                    style: TextStyle(
                      color: Colors.white70,
                      fontSize: 12,
                      height: 1.6,
                    ),
                  ),
                ],
              ),
            ),

            if (_isLoading)
              const Padding(
                padding: EdgeInsets.all(50),
                child: Center(child: CircularProgressIndicator(color: AppTheme.navy)),
              )
            else if (_errorMessage != null)
              Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    children: [
                      const Icon(Icons.error_outline, size: 40, color: Colors.red),
                      const SizedBox(height: 8),
                      Text(_errorMessage!),
                      const SizedBox(height: 12),
                      ElevatedButton(
                        onPressed: _fetch,
                        child: const Text('إعادة المحاولة'),
                      ),
                    ],
                  ),
                ),
              )
            else if (_schools.isEmpty)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(40),
                  child: Text('لا توجد مدارس ذكية مسجلة حالياً.'),
                ),
              )
            else
              ..._schools.map((s) => SchoolCard(school: s)),
          ],
        ),
      ),
    );
  }
}
