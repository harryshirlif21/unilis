<?php
/**
 * Live Engagement Module - Configuration
 * 
 * Central configuration for the Live Engagement Module.
 * 
 * @package UNILIS\LiveEngagement
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('UNILIS_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Live Engagement Module Configuration
 */
return [
    // Module metadata
    'module' => [
        'name' => 'Live Engagement',
        'version' => '1.0.0',
        'description' => 'Interactive live engagement module for presentations, polls, quizzes, whiteboards, and more.',
        'author' => 'UNILIS Team',
        'min_php_version' => '8.0',
        'dependencies' => ['Authentication', 'Courses'],
    ],

    // Session configuration
    'session' => [
        'code_length' => 8,
        'code_characters' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
        'default_duration' => 60,
        'max_duration' => 480,
        'auto_close_minutes' => 5,
    ],

    // Poll defaults
    'polls' => [
        'default_time_limit' => 120,
        'max_options' => 26,
        'min_options' => 2,
        'rating_max' => 10,
        'rating_min' => 1,
        'likert_scale' => [
            'Strongly Disagree',
            'Disagree',
            'Neutral',
            'Agree',
            'Strongly Agree',
        ],
    ],

    // Quiz defaults
    'quizzes' => [
        'default_time_limit' => 10,
        'max_time_limit' => 120,
        'max_questions' => 100,
        'max_attempts' => 3,
        'passing_score' => 50,
    ],

    // Presentation defaults
    'presentations' => [
        'max_file_size' => 52428800, // 50MB
        'allowed_types' => ['pdf', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'html'],
        'max_slides' => 200,
        'slide_width' => 1920,
        'slide_height' => 1080,
    ],

    // Whiteboard defaults
    'whiteboard' => [
        'width' => 1920,
        'height' => 1080,
        'max_objects' => 5000,
        'stroke_colors' => ['#000000', '#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF', '#FFFFFF'],
        'stroke_widths' => [1, 2, 3, 5, 8, 12],
        'font_sizes' => [12, 14, 16, 18, 24, 32, 48, 64],
    ],

    // Word cloud defaults
    'wordcloud' => [
        'max_words' => 100,
        'min_word_length' => 2,
        'max_word_length' => 50,
        'default_stop_words' => [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to',
            'for', 'of', 'by', 'with', 'is', 'are', 'was', 'were', 'be',
            'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
            'will', 'would', 'can', 'could', 'shall', 'should', 'may',
            'might', 'this', 'that', 'these', 'those', 'it', 'its',
        ],
    ],

    // Upload paths
    'uploads' => [
        'presentations' => __DIR__ . '/../uploads/presentations/',
        'temp' => __DIR__ . '/../uploads/temp/',
        'max_file_size' => 52428800, // 50MB
        'allowed_mime_types' => [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg',
            'image/png',
            'image/gif',
            'video/mp4',
            'video/webm',
            'text/html',
        ],
    ],

    // Security
    'security' => [
        'csrf_token_name' => 'le_csrf_token',
        'session_key_prefix' => 'le_',
        'rate_limit' => [
            'polls' => 5,  // max polls per minute
            'responses' => 30, // max responses per minute
            'reactions' => 20, // max reactions per minute
        ],
    ],

    // Real-time configuration
    'realtime' => [
        'polling_interval' => 3000, // ms between polling requests
        'max_reconnect_attempts' => 5,
        'reconnect_delay' => 2000, // ms
    ],

    // Theme
    'theme' => [
        'colors' => [
            'primary' => '#1E6FBA',
            'secondary' => '#D4AF37',
            'success' => '#2E8B57',
            'danger' => '#DC3545',
            'warning' => '#FFC107',
            'info' => '#17A2B8',
            'dark' => '#1F1F1F',
            'light' => '#f4f4f9',
        ],
        'glassmorphism' => [
            'background' => 'rgba(255, 255, 255, 0.15)',
            'border' => 'rgba(255, 255, 255, 0.18)',
            'shadow' => '0 8px 32px 0 rgba(31, 38, 135, 0.37)',
            'backdrop_filter' => 'blur(10px)',
        ],
    ],
];