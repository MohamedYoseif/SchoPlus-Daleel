import 'package:flutter/material.dart';
import '../models/news_item.dart';
import '../theme/app_theme.dart';
import '../services/api_service.dart';
import '../widgets/news_card.dart';

class NewsScreen extends StatefulWidget {
  const NewsScreen({super.key});

  @override
  State<NewsScreen> createState() => _NewsScreenState();
}

class _NewsScreenState extends State<NewsScreen> {
  List<NewsItem> _news = [];
  int _currentPage = 1;
  int _totalPages = 1;
  bool _isLoading = true;
  bool _isLoadingMore = false;
  String? _errorMessage;

  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _fetch();
    _scrollController.addListener(() {
      if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
        if (!_isLoadingMore && _currentPage < _totalPages) {
          _fetch(loadMore: true);
        }
      }
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _fetch({bool loadMore = false}) async {
    if (loadMore) {
      setState(() => _isLoadingMore = true);
    } else {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final nextPage = loadMore ? _currentPage + 1 : 1;
      final res = await ApiService.getNewsFeed(page: nextPage);
      if (mounted) {
        setState(() {
          _currentPage = res['page'] ?? 1;
          _totalPages = res['totalPages'] ?? 1;
          final list = res['news'] as List<NewsItem>;
          if (loadMore) {
            _news.addAll(list);
            _isLoadingMore = false;
          } else {
            _news = list;
            _isLoading = false;
          }
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _isLoadingMore = false;
          _errorMessage = e.toString().replaceAll('Exception: ', '');
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'الأخبار والفعاليات المدرسية',
          style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
        ),
      ),
      body: RefreshIndicator(
        color: AppTheme.navy,
        onRefresh: () => _fetch(),
        child: _isLoading && _news.isEmpty
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
                            onPressed: () => _fetch(),
                            child: const Text('إعادة المحاولة'),
                          ),
                        ],
                      ),
                    ),
                  )
                : _news.isEmpty
                    ? const Center(
                        child: Text('لا توجد أخبار منشورة حالياً.'),
                      )
                    : ListView.builder(
                        controller: _scrollController,
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        itemCount: _news.length + (_isLoadingMore ? 1 : 0),
                        itemBuilder: (context, i) {
                          if (i >= _news.length) {
                            return const Center(
                              child: Padding(
                                padding: EdgeInsets.all(16),
                                child: CircularProgressIndicator(strokeWidth: 2, color: AppTheme.navy),
                              ),
                            );
                          }
                          return NewsCard(item: _news[i]);
                        },
                      ),
      ),
    );
  }
}
