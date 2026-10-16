class School {
  final int id;
  final String name;
  final String nameEn;
  final String slug;
  final String schoolType;
  final String genderType;
  final String curriculum;
  final int? establishedYear;
  final String state;
  final String city;
  final String district;
  final String address;
  final String phone;
  final String whatsapp;
  final String email;
  final String website;
  final String googleMapUrl;
  final String logo;
  final String cover;
  final String description;
  final String vision;
  final String mission;
  final String approxFees;
  final String feesType;
  final List<dynamic> stageFees;
  final List<dynamic> feeDiscounts;
  final bool isSchoplusClient;
  final bool isVerified;
  final bool isFeatured;
  final double ratingAvg;
  final int reviewsCount;
  final int viewsCount;
  final int? directoryRank;
  final List<String> stages;
  final List<String> facilities;
  final List<String> gallery;
  final List<dynamic> branches;
  final String shareUrl;

  School({
    required this.id,
    required this.name,
    this.nameEn = '',
    this.slug = '',
    this.schoolType = 'خاصة',
    this.genderType = 'مختلط',
    this.curriculum = 'سوداني',
    this.establishedYear,
    required this.state,
    required this.city,
    this.district = '',
    this.address = '',
    this.phone = '',
    this.whatsapp = '',
    this.email = '',
    this.website = '',
    this.googleMapUrl = '',
    required this.logo,
    this.cover = '',
    this.description = '',
    this.vision = '',
    this.mission = '',
    this.approxFees = '',
    this.feesType = 'average',
    this.stageFees = const [],
    this.feeDiscounts = const [],
    this.isSchoplusClient = false,
    this.isVerified = false,
    this.isFeatured = false,
    this.ratingAvg = 0.0,
    this.reviewsCount = 0,
    this.viewsCount = 0,
    this.directoryRank,
    this.stages = const [],
    this.facilities = const [],
    this.gallery = const [],
    this.branches = const [],
    this.shareUrl = '',
  });

  factory School.fromJson(Map<String, dynamic> json) {
    return School(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id'].toString()) ?? 0,
      name: json['name']?.toString() ?? '',
      nameEn: json['name_en']?.toString() ?? '',
      slug: json['slug']?.toString() ?? '',
      schoolType: json['school_type']?.toString() ?? 'خاصة',
      genderType: json['gender_type']?.toString() ?? 'مختلط',
      curriculum: json['curriculum']?.toString() ?? 'سوداني',
      establishedYear: json['established_year'] != null
          ? int.tryParse(json['established_year'].toString())
          : null,
      state: json['state']?.toString() ?? '',
      city: json['city']?.toString() ?? '',
      district: json['district']?.toString() ?? '',
      address: json['address']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      whatsapp: json['whatsapp']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      website: json['website']?.toString() ?? '',
      googleMapUrl: json['google_map_url']?.toString() ?? '',
      logo: json['logo']?.toString() ?? '',
      cover: json['cover']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      vision: json['vision']?.toString() ?? '',
      mission: json['mission']?.toString() ?? '',
      approxFees: json['approx_fees']?.toString() ?? '',
      feesType: json['fees_type']?.toString() ?? 'average',
      stageFees: json['stage_fees'] is List ? json['stage_fees'] : [],
      feeDiscounts: json['fee_discounts'] is List ? json['fee_discounts'] : [],
      isSchoplusClient: json['is_schoplus_client'] == true || json['is_schoplus_client'] == 1,
      isVerified: json['is_verified'] == true || json['is_verified'] == 1,
      isFeatured: json['is_featured'] == true || json['is_featured'] == 1,
      ratingAvg: (json['rating_avg'] != null)
          ? double.tryParse(json['rating_avg'].toString()) ?? 0.0
          : 0.0,
      reviewsCount: json['reviews_count'] is int
          ? json['reviews_count']
          : int.tryParse(json['reviews_count']?.toString() ?? '0') ?? 0,
      viewsCount: json['views_count'] is int
          ? json['views_count']
          : int.tryParse(json['views_count']?.toString() ?? '0') ?? 0,
      directoryRank: json['directory_rank'] != null
          ? int.tryParse(json['directory_rank'].toString())
          : null,
      stages: (json['stages'] is List)
          ? List<String>.from(json['stages'].map((e) => e.toString()))
          : [],
      facilities: (json['facilities'] is List)
          ? List<String>.from(json['facilities'].map((e) => e.toString()))
          : [],
      gallery: (json['gallery'] is List)
          ? List<String>.from(json['gallery'].map((e) => e.toString()))
          : [],
      branches: json['branches'] is List ? json['branches'] : [],
      shareUrl: json['share_url']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'name_en': nameEn,
      'slug': slug,
      'school_type': schoolType,
      'gender_type': genderType,
      'curriculum': curriculum,
      'established_year': establishedYear,
      'state': state,
      'city': city,
      'district': district,
      'address': address,
      'phone': phone,
      'whatsapp': whatsapp,
      'email': email,
      'website': website,
      'google_map_url': googleMapUrl,
      'logo': logo,
      'cover': cover,
      'description': description,
      'vision': vision,
      'mission': mission,
      'approx_fees': approxFees,
      'fees_type': feesType,
      'stage_fees': stageFees,
      'fee_discounts': feeDiscounts,
      'is_schoplus_client': isSchoplusClient,
      'is_verified': isVerified,
      'is_featured': isFeatured,
      'rating_avg': ratingAvg,
      'reviews_count': reviewsCount,
      'views_count': viewsCount,
      'directory_rank': directoryRank,
      'stages': stages,
      'facilities': facilities,
      'gallery': gallery,
      'branches': branches,
      'share_url': shareUrl,
    };
  }
}
