import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/school.dart';
import '../theme/app_theme.dart';
import '../services/api_service.dart';

class AdmissionDialog extends StatefulWidget {
  final School school;

  const AdmissionDialog({super.key, required this.school});

  static Future<void> show(BuildContext context, School school) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => AdmissionDialog(school: school),
    );
  }

  @override
  State<AdmissionDialog> createState() => _AdmissionDialogState();
}

class _AdmissionDialogState extends State<AdmissionDialog> {
  final _formKey = GlobalKey<FormState>();
  final _parentNameController = TextEditingController();
  final _parentPhoneController = TextEditingController();
  final _studentNameController = TextEditingController();
  final _notesController = TextEditingController();

  String _selectedStage = '';
  bool _needsTransportation = false;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    if (widget.school.stages.isNotEmpty) {
      _selectedStage = widget.school.stages.first;
    }
  }

  @override
  void dispose() {
    _parentNameController.dispose();
    _parentPhoneController.dispose();
    _studentNameController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSubmitting = true);

    try {
      final res = await ApiService.submitInquiry(
        schoolId: widget.school.id,
        parentName: _parentNameController.text.trim(),
        parentPhone: _parentPhoneController.text.trim(),
        studentName: _studentNameController.text.trim().isNotEmpty
            ? _studentNameController.text.trim()
            : null,
        studentStage: _selectedStage.isNotEmpty ? _selectedStage : null,
        needsTransportation: _needsTransportation,
        message: _notesController.text.trim().isNotEmpty
            ? _notesController.text.trim()
            : null,
      );

      if (!mounted) return;

      Navigator.pop(context);

      _showSuccessDialog(res['message'] ?? 'تم إرسال طلبكم بنجاح!');
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSubmitting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString().replaceAll('Exception: ', '')),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  void _showSuccessDialog(String message) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(
          children: [
            Icon(Icons.check_circle, color: AppTheme.emerald, size: 28),
            SizedBox(width: 8),
            Text('تم إرسال الطلب', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          ],
        ),
        content: Text(
          message,
          style: const TextStyle(fontSize: 13.5, height: 1.5),
        ),
        actions: [
          if (widget.school.whatsapp.isNotEmpty)
            ElevatedButton.icon(
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF25D366),
                foregroundColor: Colors.white,
              ),
              onPressed: () async {
                Navigator.pop(ctx);
                final cleanPhone = widget.school.whatsapp.replaceAll(RegExp(r'[^0-9]'), '');
                final text = Uri.encodeComponent(
                  'مرحباً إدارة مدرسة ${widget.school.name}، قمت بتقديم طلب استفسار عبر تطبيق دليل المدارس السودانية باسم: ${_parentNameController.text.trim()}',
                );
                final url = Uri.parse('https://wa.me/$cleanPhone?text=$text');
                if (await canLaunchUrl(url)) {
                  await launchUrl(url, mode: LaunchMode.externalApplication);
                }
              },
              icon: const Icon(Icons.chat_bubble_outline_rounded, size: 18),
              label: const Text('متابعة عبر واتساب المدرسة'),
            ),
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('إغلاق'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Container(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: bottomInset + 20,
      ),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Handle bar
              Center(
                child: Container(
                  width: 44,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AppTheme.borderStrong,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Title
              Row(
                children: [
                  const Icon(Icons.assignment_outlined, color: AppTheme.navy, size: 24),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'طلب قبول واستفسار',
                          style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w900,
                            color: AppTheme.textHeading,
                          ),
                        ),
                        Text(
                          widget.school.name,
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppTheme.blue,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
              ),

              const SizedBox(height: 16),
              const Divider(height: 1, color: AppTheme.border),
              const SizedBox(height: 16),

              // Parent Name
              const Text('اسم ولي الأمر *', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
              const SizedBox(height: 6),
              TextFormField(
                controller: _parentNameController,
                decoration: const InputDecoration(
                  hintText: 'مثال: محمد يوسف أحمد',
                  prefixIcon: Icon(Icons.person_outline, size: 20),
                ),
                validator: (v) => (v == null || v.trim().isEmpty) ? 'يرجى إدخال اسم ولي الأمر' : null,
              ),

              const SizedBox(height: 14),

              // Phone / WhatsApp
              const Text('رقم الهاتف / الواتساب *', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
              const SizedBox(height: 6),
              TextFormField(
                controller: _parentPhoneController,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  hintText: '0912345678 أو +249...',
                  prefixIcon: Icon(Icons.phone_outlined, size: 20),
                ),
                validator: (v) => (v == null || v.trim().isEmpty) ? 'يرجى إدخال رقم الهاتف للتواصل' : null,
              ),

              const SizedBox(height: 14),

              // Student Name
              const Text('اسم الطالب (اختياري)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
              const SizedBox(height: 6),
              TextFormField(
                controller: _studentNameController,
                decoration: const InputDecoration(
                  hintText: 'اسم الطالب المراد تسجيله',
                  prefixIcon: Icon(Icons.school_outlined, size: 20),
                ),
              ),

              const SizedBox(height: 14),

              // Stage Selector
              if (widget.school.stages.isNotEmpty) ...[
                const Text('المرحلة الدراسية المطلوبة', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  children: widget.school.stages.map((st) {
                    final isSel = _selectedStage == st;
                    return ChoiceChip(
                      label: Text(st),
                      selected: isSel,
                      selectedColor: AppTheme.navy,
                      labelStyle: TextStyle(
                        color: isSel ? Colors.white : AppTheme.textHeading,
                        fontWeight: isSel ? FontWeight.bold : FontWeight.normal,
                        fontSize: 12,
                      ),
                      onSelected: (val) {
                        setState(() => _selectedStage = st);
                      },
                    );
                  }).toList(),
                ),
                const SizedBox(height: 14),
              ],

              // Transportation Checkbox
              CheckboxListTile(
                value: _needsTransportation,
                onChanged: (val) => setState(() => _needsTransportation = val ?? false),
                title: const Text('هل ترغب في خدمة ترحيل (باص مدرسي)؟', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                controlAffinity: ListTileControlAffinity.leading,
                contentPadding: EdgeInsets.zero,
                activeColor: AppTheme.blue,
              ),

              const SizedBox(height: 8),

              // Notes / Message
              const Text('ملاحظات أو استفسارات إضافية', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
              const SizedBox(height: 6),
              TextFormField(
                controller: _notesController,
                maxLines: 3,
                decoration: const InputDecoration(
                  hintText: 'اكتب استفساراتك عن المصاريف، الفروع، أو سياسة القبول...',
                ),
              ),

              const SizedBox(height: 20),

              // Submit Button
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _isSubmitting ? null : _submit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.gold,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  child: _isSubmitting
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(strokeWidth: 2, color: AppTheme.navy),
                        )
                      : const Text(
                          'إرسال طلب الاستفسار للمدرسة',
                          style: TextStyle(
                            color: AppTheme.navy,
                            fontSize: 15,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
