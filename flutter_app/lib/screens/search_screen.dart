import 'dart:async';
import 'package:flutter/material.dart';
import '../models/school.dart';
import '../theme/app_theme.dart';
import '../services/api_service.dart';
import '../widgets/school_card.dart';

class SearchScreen extends StatefulWidget {
  final String? initialState;
  final bool initialSchoplusOnly;

  const SearchScreen({
    super.key,
    this.initialState,
    this.initialSchoplusOnly = false,
  });

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  final _searchController = TextEditingController();
  Timer? _debounceTimer;

  String _searchQuery = '';
  String _selectedState = '';
  String _selectedCity = '';
  String _selectedStage = '';
  String _selectedType = '';
  String _selectedCurriculum = '';
  bool _schoplusOnly = false;

  Map<String, List<String>> _statesWithCities = {};
  List<String> _stagesList = [];
  List<String> _typesList = [];

  List<School> _schools = [];
  int _totalSchools = 0;
  int _currentPage = 1;
  int _totalPages = 1;
  bool _isLoading = false;
  bool _isLoadingMore = false;
  String? _errorMessage;

  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    if (widget.initialState != null && widget.initialState!.isNotEmpty) {
      _selectedState = widget.initialState!;
    }
    _schoplusOnly = widget.initialSchoplusOnly;

    _loadMeta();
    _fetchSchools();

    _scrollController.addListener(() {
      if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
        if (!_isLoadingMore && _currentPage < _totalPages) {
          _fetchSchools(loadMore: true);
        }
      }
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    _debounceTimer?.cancel();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _loadMeta() async {
    try {
      final meta = await ApiService.getMeta();
      if (mounted) {
        setState(() {
          if (meta['states_with_cities'] is Map) {
            final raw = meta['states_with_cities'] as Map;
            _statesWithCities = raw.map((k, v) => MapEntry(
                  k.toString(),
                  (v is List) ? List<String>.from(v.map((e) => e.toString())) : <String>[],
                ));
          }
          if (meta['stages'] is List) {
            _stagesList = List<String>.from(meta['stages'].map((e) => e.toString()));
          }
          if (meta['types'] is List) {
            _typesList = List<String>.from(meta['types'].map((e) => e.toString()));
          }
        });
      }
    } catch (_) {}
  }

  Future<void> _fetchSchools({bool loadMore = false}) async {
    if (loadMore) {
      setState(() => _isLoadingMore = true);
    } else {
      setState(() {
        _isLoading = true;
        _currentPage = 1;
        _errorMessage = null;
      });
    }

    try {
      final nextPage = loadMore ? _currentPage + 1 : 1;
      final res = await ApiService.getSchools(
        q: _searchQuery,
        state: _selectedState,
        city: _selectedCity,
        stage: _selectedStage,
        type: _selectedType,
        curriculum: _selectedCurriculum,
        schoplusOnly: _schoplusOnly,
        page: nextPage,
      );

      if (!mounted) return;

      setState(() {
        _totalSchools = res['total'] ?? 0;
        _currentPage = res['page'] ?? 1;
        _totalPages = res['totalPages'] ?? 1;
        final list = res['schools'] as List<School>;

        if (loadMore) {
          _schools.addAll(list);
          _isLoadingMore = false;
        } else {
          _schools = list;
          _isLoading = false;
        }
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _isLoadingMore = false;
        _errorMessage = e.toString().replaceAll('Exception: ', '');
      });
    }
  }

  void _onSearchChanged(String val) {
    _debounceTimer?.cancel();
    _debounceTimer = Timer(const Duration(milliseconds: 350), () {
      setState(() {
        _searchQuery = val.trim();
      });
      _fetchSchools();
    });
  }

  void _openFiltersSheet() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            final citiesForSelectedState = _selectedState.isNotEmpty && _statesWithCities.containsKey(_selectedState)
                ? _statesWithCities[_selectedState]!
                : <String>[];

            return Container(
              padding: const EdgeInsets.all(20),
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
              ),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Center(
                      child: Container(
                        width: 40,
                        height: 4,
                        decoration: BoxDecoration(
                          color: AppTheme.borderStrong,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'تصفية وفلترة المدارس',
                          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: AppTheme.navy),
                        ),
                        TextButton(
                          onPressed: () {
                            setModalState(() {
                              _selectedState = '';
                              _selectedCity = '';
                              _selectedStage = '';
                              _selectedType = '';
                              _selectedCurriculum = '';
                              _schoplusOnly = false;
                            });
                          },
                          child: const Text('إعادة ضبط', style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold, fontSize: 12)),
                        ),
                      ],
                    ),
                    const Divider(height: 1, color: AppTheme.border),
                    const SizedBox(height: 14),

                    // State Dropdown
                    const Text('الولاية', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                    const SizedBox(height: 6),
                    DropdownButtonFormField<String>(
                      initialValue: _selectedState.isEmpty ? null : _selectedState,
                      decoration: const InputDecoration(hintText: 'كافة الولايات'),
                      items: [
                        const DropdownMenuItem(value: '', child: Text('كافة الولايات')),
                        ..._statesWithCities.keys.map((s) => DropdownMenuItem(value: s, child: Text(s))),
                      ],
                      onChanged: (val) {
                        setModalState(() {
                          _selectedState = val ?? '';
                          _selectedCity = '';
                        });
                      },
                    ),

                    const SizedBox(height: 12),

                    // City Dropdown
                    if (citiesForSelectedState.isNotEmpty) ...[
                      const Text('المدينة / المحلية', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                      const SizedBox(height: 6),
                      DropdownButtonFormField<String>(
                        initialValue: _selectedCity.isEmpty ? null : _selectedCity,
                        decoration: const InputDecoration(hintText: 'كافة المدن والمحليات'),
                        items: [
                          const DropdownMenuItem(value: '', child: Text('كافة المدن')),
                          ...citiesForSelectedState.map((c) => DropdownMenuItem(value: c, child: Text(c))),
                        ],
                        onChanged: (val) {
                          setModalState(() => _selectedCity = val ?? '');
                        },
                      ),
                      const SizedBox(height: 12),
                    ],

                    // Stage Selector
                    const Text('المرحلة الدراسية', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 6,
                      children: [
                        _modalChip('الكل', _selectedStage.isEmpty, () {
                          setModalState(() => _selectedStage = '');
                        }),
                        ...(_stagesList.isNotEmpty ? _stagesList : ['رياض أطفال', 'ابتدائي', 'متوسط', 'ثانوي']).map((st) {
                          return _modalChip(st, _selectedStage == st, () {
                            setModalState(() => _selectedStage = st);
                          });
                        }),
                      ],
                    ),

                    const SizedBox(height: 14),

                    // School Type Selector
                    if (_typesList.isNotEmpty) ...[
                      const Text('نوع المدرسة', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 6,
                        children: [
                          _modalChip('الكل', _selectedType.isEmpty, () {
                            setModalState(() => _selectedType = '');
                          }),
                          ..._typesList.map((tp) {
                            return _modalChip(tp, _selectedType == tp, () {
                              setModalState(() => _selectedType = tp);
                            });
                          }),
                        ],
                      ),
                      const SizedBox(height: 14),
                    ],

                    // Smart Schools Only Switch
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      value: _schoplusOnly,
                      activeThumbColor: AppTheme.gold,
                      title: const Text('المدارس الذكية فقط (SchoPlus)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                      subtitle: const Text('عرض المدارس التي تتيح خدمات الإدارة الذكية والقبول الفوري', style: TextStyle(fontSize: 11)),
                      onChanged: (val) => setModalState(() => _schoplusOnly = val),
                    ),

                    const SizedBox(height: 16),

                    // Apply Button
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: () {
                          Navigator.pop(ctx);
                          _fetchSchools();
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppTheme.navy,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                        ),
                        child: const Text('تطبيق الفلاتر', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _modalChip(String text, bool isSelected, VoidCallback onTap) {
    return ChoiceChip(
      label: Text(text),
      selected: isSelected,
      selectedColor: AppTheme.navy,
      labelStyle: TextStyle(
        color: isSelected ? Colors.white : AppTheme.textHeading,
        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
        fontSize: 12,
      ),
      onSelected: (_) => onTap(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final hasActiveFilters = _selectedState.isNotEmpty ||
        _selectedCity.isNotEmpty ||
        _selectedStage.isNotEmpty ||
        _selectedType.isNotEmpty ||
        _schoplusOnly;

    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'استكشاف وبحث المدارس',
          style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
        ),
      ),
      body: Column(
        children: [
          // Search Input & Filter Button
          Container(
            color: Colors.white,
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _searchController,
                    onChanged: _onSearchChanged,
                    decoration: InputDecoration(
                      hintText: 'ابحث بالاسم، الولاية، أو الحي...',
                      prefixIcon: const Icon(Icons.search, color: AppTheme.blue),
                      suffixIcon: _searchController.text.isNotEmpty
                          ? IconButton(
                              icon: const Icon(Icons.clear, size: 18),
                              onPressed: () {
                                _searchController.clear();
                                _onSearchChanged('');
                              },
                            )
                          : null,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Badge(
                  isLabelVisible: hasActiveFilters,
                  backgroundColor: AppTheme.gold,
                  child: IconButton.filled(
                    style: IconButton.styleFrom(
                      backgroundColor: hasActiveFilters ? AppTheme.navy : AppTheme.bgSlate,
                      foregroundColor: hasActiveFilters ? Colors.white : AppTheme.navy,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                        side: const BorderSide(color: AppTheme.border),
                      ),
                    ),
                    icon: const Icon(Icons.tune),
                    onPressed: _openFiltersSheet,
                  ),
                ),
              ],
            ),
          ),

          // Active Filter Chips Bar
          if (hasActiveFilters)
            Container(
              height: 40,
              color: Colors.white,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                children: [
                  if (_selectedState.isNotEmpty)
                    _chipTag('الولاية: $_selectedState', () {
                      setState(() {
                        _selectedState = '';
                        _selectedCity = '';
                      });
                      _fetchSchools();
                    }),
                  if (_selectedCity.isNotEmpty)
                    _chipTag('المدينة: $_selectedCity', () {
                      setState(() => _selectedCity = '');
                      _fetchSchools();
                    }),
                  if (_selectedStage.isNotEmpty)
                    _chipTag('المرحلة: $_selectedStage', () {
                      setState(() => _selectedStage = '');
                      _fetchSchools();
                    }),
                  if (_schoplusOnly)
                    _chipTag('مدارس ذكية فقط', () {
                      setState(() => _schoplusOnly = false);
                      _fetchSchools();
                    }),
                  TextButton(
                    onPressed: () {
                      setState(() {
                        _selectedState = '';
                        _selectedCity = '';
                        _selectedStage = '';
                        _schoplusOnly = false;
                      });
                      _fetchSchools();
                    },
                    child: const Text('مسح الكل', style: TextStyle(fontSize: 11, color: Colors.red, fontWeight: FontWeight.bold)),
                  ),
                ],
              ),
            ),

          // Results Count Bar
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            color: AppTheme.bgSlate,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'نتائج البحث ($_totalSchools مدرسة)',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: AppTheme.textMuted,
                  ),
                ),
                if (_isLoading)
                  const SizedBox(
                    width: 14,
                    height: 14,
                    child: CircularProgressIndicator(strokeWidth: 2, color: AppTheme.navy),
                  ),
              ],
            ),
          ),

          // Schools List View
          Expanded(
            child: _isLoading && _schools.isEmpty
                ? const Center(child: CircularProgressIndicator(color: AppTheme.navy))
                : _errorMessage != null
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(Icons.error_outline, size: 40, color: Colors.red),
                              const SizedBox(height: 8),
                              Text(_errorMessage!),
                              const SizedBox(height: 12),
                              ElevatedButton(
                                onPressed: () => _fetchSchools(),
                                child: const Text('إعادة المحاولة'),
                              ),
                            ],
                          ),
                        ),
                      )
                    : _schools.isEmpty
                        ? const Center(
                            child: Padding(
                              padding: EdgeInsets.all(30),
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.search_off, size: 54, color: AppTheme.textMuted),
                                  SizedBox(height: 12),
                                  Text(
                                    'لا توجد مدارس مطابقة لبحثك',
                                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                                  ),
                                  SizedBox(height: 6),
                                  Text(
                                    'جرّب البحث باسم آخر أو إزالة بعض الفلاتر.',
                                    style: TextStyle(color: AppTheme.textMuted, fontSize: 12),
                                  ),
                                ],
                              ),
                            ),
                          )
                        : ListView.builder(
                            controller: _scrollController,
                            padding: const EdgeInsets.symmetric(vertical: 8),
                            itemCount: _schools.length + (_isLoadingMore ? 1 : 0),
                            itemBuilder: (context, i) {
                              if (i >= _schools.length) {
                                return const Center(
                                  child: Padding(
                                    padding: EdgeInsets.all(16),
                                    child: CircularProgressIndicator(strokeWidth: 2, color: AppTheme.navy),
                                  ),
                                );
                              }
                              return SchoolCard(school: _schools[i]);
                            },
                          ),
          ),
        ],
      ),
    );
  }

  Widget _chipTag(String label, VoidCallback onRemove) {
    return Container(
      margin: const EdgeInsets.only(left: 6, top: 4, bottom: 4),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
      decoration: BoxDecoration(
        color: AppTheme.navy.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppTheme.navy.withValues(alpha: 0.2)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.navy)),
          const SizedBox(width: 4),
          InkWell(
            onTap: onRemove,
            child: const Icon(Icons.close, size: 14, color: AppTheme.navy),
          ),
        ],
      ),
    );
  }
}
