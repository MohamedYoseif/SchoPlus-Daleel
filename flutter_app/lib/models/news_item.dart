class NewsItem {
  final int id;
  final int schoolId;
  final String schoolName;
  final String schoolLogo;
  final String state;
  final String city;
  final String branchName;
  final String title;
  final String content;
  final String htmlContent;
  final String image;
  final String publishedAt;
  final int viewsCount;

  NewsItem({
    required this.id,
    required this.schoolId,
    required this.schoolName,
    required this.schoolLogo,
    this.state = '',
    this.city = '',
    this.branchName = '',
    required this.title,
    required this.content,
    this.htmlContent = '',
    this.image = '',
    required this.publishedAt,
    this.viewsCount = 0,
  });

  factory NewsItem.fromJson(Map<String, dynamic> json) {
    return NewsItem(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id'].toString()) ?? 0,
      schoolId: json['school_id'] is int ? json['school_id'] : int.tryParse(json['school_id'].toString()) ?? 0,
      schoolName: json['school_name']?.toString() ?? '',
      schoolLogo: json['school_logo']?.toString() ?? '',
      state: json['state']?.toString() ?? '',
      city: json['city']?.toString() ?? '',
      branchName: json['branch_name']?.toString() ?? '',
      title: json['title']?.toString() ?? '',
      content: json['content']?.toString() ?? '',
      htmlContent: json['html_content']?.toString() ?? '',
      image: json['image']?.toString() ?? '',
      publishedAt: json['published_at']?.toString() ?? '',
      viewsCount: json['views_count'] is int ? json['views_count'] : int.tryParse(json['views_count']?.toString() ?? '0') ?? 0,
    );
  }
}
