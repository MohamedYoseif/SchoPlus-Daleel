import 'school.dart';
import 'news_item.dart';

class StateCount {
  final String name;
  final int count;

  StateCount({required this.name, required this.count});

  factory StateCount.fromJson(Map<String, dynamic> json) {
    return StateCount(
      name: json['name']?.toString() ?? '',
      count: json['count'] is int ? json['count'] : int.tryParse(json['count']?.toString() ?? '0') ?? 0,
    );
  }
}

class HomeStats {
  final int totalSchools;
  final int schoplusSchools;
  final int statesCount;

  HomeStats({
    required this.totalSchools,
    required this.schoplusSchools,
    required this.statesCount,
  });

  factory HomeStats.fromJson(Map<String, dynamic> json) {
    return HomeStats(
      totalSchools: json['total_schools'] is int ? json['total_schools'] : int.tryParse(json['total_schools']?.toString() ?? '0') ?? 0,
      schoplusSchools: json['schoplus_schools'] is int ? json['schoplus_schools'] : int.tryParse(json['schoplus_schools']?.toString() ?? '0') ?? 0,
      statesCount: json['states_count'] is int ? json['states_count'] : int.tryParse(json['states_count']?.toString() ?? '0') ?? 18,
    );
  }
}

class HomeData {
  final HomeStats stats;
  final List<StateCount> states;
  final List<School> schoplusSchools;
  final List<School> topRatedSchools;
  final List<School> latestSchools;
  final List<NewsItem> latestNews;

  HomeData({
    required this.stats,
    required this.states,
    required this.schoplusSchools,
    required this.topRatedSchools,
    required this.latestSchools,
    required this.latestNews,
  });

  factory HomeData.fromJson(Map<String, dynamic> json) {
    final statsJson = json['stats'] is Map<String, dynamic> ? json['stats'] : <String, dynamic>{};
    final statesList = (json['states'] is List)
        ? (json['states'] as List).map((e) => StateCount.fromJson(e as Map<String, dynamic>)).toList()
        : <StateCount>[];
    final schoplusList = (json['schoplus_schools'] is List)
        ? (json['schoplus_schools'] as List).map((e) => School.fromJson(e as Map<String, dynamic>)).toList()
        : <School>[];
    final topRatedList = (json['top_rated_schools'] is List)
        ? (json['top_rated_schools'] as List).map((e) => School.fromJson(e as Map<String, dynamic>)).toList()
        : <School>[];
    final latestList = (json['latest_schools'] is List)
        ? (json['latest_schools'] as List).map((e) => School.fromJson(e as Map<String, dynamic>)).toList()
        : <School>[];
    final newsList = (json['latest_news'] is List)
        ? (json['latest_news'] as List).map((e) => NewsItem.fromJson(e as Map<String, dynamic>)).toList()
        : <NewsItem>[];

    return HomeData(
      stats: HomeStats.fromJson(statsJson),
      states: statesList,
      schoplusSchools: schoplusList,
      topRatedSchools: topRatedList,
      latestSchools: latestList,
      latestNews: newsList,
    );
  }
}
