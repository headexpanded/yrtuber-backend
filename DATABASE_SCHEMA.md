# Database Schema Documentation

## Overview
This document describes the complete database schema for the YouTube curation platform (yrtuber). The schema is designed to support a social platform where users can create curated collections of YouTube videos, follow other curators, and discover content through human curation.

## Core Tables

### 1. users
**Purpose**: Laravel's default user authentication table
- `id` - Primary key
- `name` - User's display name
- `email` - Unique email address
- `email_verified_at` - Email verification timestamp
- `password` - Hashed password
- `remember_token` - For "remember me" functionality
- `created_at`, `updated_at` - Timestamps

### 2. user_profiles
**Purpose**: Extended user profile information
- `id` - Primary key
- `user_id` - Foreign key to users table
- `username` - Unique username for profile URLs
- `bio` - User biography/description
- `avatar` - Profile picture URL
- `website` - Personal website URL
- `location` - User's location
- `social_links` - JSON field for social media links
- `is_verified` - Verification badge status
- `is_featured_curator` - Featured curator status
- `follower_count` - Cached follower count
- `following_count` - Cached following count
- `collection_count` - Cached collection count
- `created_at`, `updated_at` - Timestamps

**Indexes**: `username`, `is_verified, is_featured_curator`

### 3. collections
**Purpose**: Curated video collections created by users
- `id` - Primary key
- `user_id` - Foreign key to users table (curator)
- `title` - Collection title
- `slug` - URL-friendly slug
- `description` - Collection description
- `cover_image` - Collection cover image URL
- `layout` - Display layout (grid, list, carousel, magazine)
- `is_public` - Public/private visibility
- `is_featured` - Featured collection status
- `view_count` - Cached view count
- `like_count` - Cached like count
- `video_count` - Cached video count
- `created_at`, `updated_at` - Timestamps

**Indexes**: `user_id, is_public`, `is_featured, created_at`, `view_count, created_at`

### 4. videos
**Purpose**: YouTube video metadata
- `id` - Primary key
- `youtube_id` - Unique YouTube video ID
- `title` - Video title
- `description` - Video description
- `thumbnail_url` - Video thumbnail URL
- `channel_name` - YouTube channel name
- `channel_id` - YouTube channel ID
- `duration` - Video duration in seconds
- `published_at` - Video publication date
- `view_count` - YouTube view count
- `like_count` - YouTube like count
- `metadata` - JSON field for additional YouTube data
- `created_at`, `updated_at` - Timestamps

**Indexes**: `youtube_id`, `channel_id`, `published_at`

### 5. tags
**Purpose**: Categorization system for collections and videos
- `id` - Primary key
- `name` - Tag name (unique)
- `slug` - URL-friendly slug (unique)
- `description` - Tag description
- `color` - Hex color for display
- `is_featured` - Featured tag status
- `created_at`, `updated_at` - Timestamps

**Indexes**: `name, is_featured`

## Relationship Tables

### 6. collection_video
**Purpose**: Many-to-many relationship between collections and videos
- `id` - Primary key
- `collection_id` - Foreign key to collections table
- `video_id` - Foreign key to videos table
- `curator_notes` - Curator's commentary on the video
- `position` - Order within the collection
- `added_at` - When video was added to collection
- `created_at`, `updated_at` - Timestamps

**Constraints**: Unique `collection_id, video_id`
**Indexes**: `collection_id, position`

### 7. collection_tag
**Purpose**: Many-to-many relationship between collections and tags
- `id` - Primary key
- `collection_id` - Foreign key to collections table
- `tag_id` - Foreign key to tags table
- `created_at`, `updated_at` - Timestamps

**Constraints**: Unique `collection_id, tag_id`

### 8. follows
**Purpose**: User following relationships
- `id` - Primary key
- `follower_id` - Foreign key to users table (who is following)
- `following_id` - Foreign key to users table (who is being followed)
- `created_at`, `updated_at` - Timestamps

**Constraints**: Unique `follower_id, following_id`
**Indexes**: `following_id, created_at`

## Social Features Tables

### 9. likes
**Purpose**: Polymorphic likes system for collections, videos, and comments
- `id` - Primary key
- `user_id` - Foreign key to users table
- `likeable_type` - Polymorphic type (App\Models\Collection, App\Models\Video, etc.)
- `likeable_id` - Polymorphic ID
- `created_at`, `updated_at` - Timestamps

**Constraints**: Unique `user_id, likeable_type, likeable_id`
**Indexes**: Automatically created by `morphs()` method

### 10. comments
**Purpose**: Polymorphic comments system with nested replies
- `id` - Primary key
- `user_id` - Foreign key to users table
- `commentable_type` - Polymorphic type (App\Models\Collection, App\Models\Video)
- `commentable_id` - Polymorphic ID
- `parent_id` - Foreign key to comments table (for nested replies)
- `content` - Comment text
- `is_approved` - Comment approval status
- `created_at`, `updated_at` - Timestamps

**Indexes**: `parent_id`, `user_id, created_at`

## Analytics & Tracking Tables

### 11. activity_logs
**Purpose**: User activity tracking for analytics
- `id` - Primary key
- `user_id` - Foreign key to users table (nullable for anonymous actions)
- `action` - Action type (e.g., 'collection.created', 'video.added')
- `subject_type` - Polymorphic type of the object being acted upon
- `subject_id` - Polymorphic ID
- `properties` - JSON field for additional action data
- `ip_address` - User's IP address
- `user_agent` - User's browser/device info
- `created_at`, `updated_at` - Timestamps

**Indexes**: `user_id, created_at`, `action, created_at`

### 12. collection_shares
**Purpose**: Track collection sharing across platforms
- `id` - Primary key
- `collection_id` - Foreign key to collections table
- `user_id` - Foreign key to users table (who shared it, nullable)
- `platform` - Sharing platform (twitter, facebook, email, link)
- `shared_url` - URL where it was shared
- `metadata` - JSON field for platform-specific data
- `created_at`, `updated_at` - Timestamps

**Indexes**: `collection_id, created_at`, `platform, created_at`

## System Tables

### 13. notifications
**Purpose**: Laravel's notification system
- `id` - UUID primary key
- `type` - Notification type
- `notifiable_type` - Polymorphic type (usually App\Models\User)
- `notifiable_id` - Polymorphic ID
- `data` - Notification data
- `read_at` - When notification was read
- `created_at`, `updated_at` - Timestamps

**Indexes**: `read_at`

### 14. sessions
**Purpose**: Laravel's session management
- `id` - Session ID
- `user_id` - Foreign key to users table (nullable)
- `ip_address` - User's IP address
- `user_agent` - User's browser/device info
- `payload` - Session data
- `last_activity` - Last activity timestamp

**Indexes**: `user_id`, `last_activity`

### 15. cache, cache_locks, jobs, failed_jobs, job_batches
**Purpose**: Laravel's caching and queue system tables

## Key Features Supported

### 1. User Management
- User registration and authentication
- Extended profiles with social links
- Verification and featured curator badges
- Follower/following relationships

### 2. Content Curation
- Create themed video collections
- Add curator notes to videos
- Multiple layout options
- Public/private collections
- Featured collections

### 3. Social Features
- Like collections, videos, and comments
- Comment system with nested replies
- Follow other curators
- Share collections across platforms

### 4. Discovery & Organization
- Tag-based categorization
- Search and filtering capabilities
- Trending and featured content
- Activity tracking

### 5. Analytics
- View counts and engagement metrics
- User activity logging
- Collection sharing tracking
- Performance optimization through caching

## Database Relationships

### One-to-One
- `users` ↔ `user_profiles`

### One-to-Many
- `users` → `collections` (curator)
- `users` → `comments` (author)
- `users` → `likes` (user who liked)
- `collections` → `collection_video` (collection)
- `videos` → `collection_video` (video)
- `comments` → `comments` (parent/child replies)

### Many-to-Many
- `collections` ↔ `videos` (via `collection_video`)
- `collections` ↔ `tags` (via `collection_tag`)
- `users` ↔ `users` (via `follows` - follower/following)

### Polymorphic
- `likes` → `collections`, `videos`, `comments`
- `comments` → `collections`, `videos`
- `activity_logs` → `collections`, `videos`, `users`
- `notifications` → `users`

## Performance Considerations

1. **Indexing**: Strategic indexes on frequently queried columns
2. **Caching**: Denormalized count fields for performance
3. **Polymorphic Relationships**: Efficient querying with proper indexes
4. **Soft Deletes**: Consider implementing for data retention
5. **Partitioning**: For large tables like activity_logs

## Future Enhancements

1. **Search**: Full-text search indexes on titles and descriptions
2. **Geolocation**: Location-based discovery features
3. **Recommendations**: User behavior tracking for recommendations
4. **Monetization**: Tables for premium features and payments
5. **Moderation**: Content moderation and reporting system 
