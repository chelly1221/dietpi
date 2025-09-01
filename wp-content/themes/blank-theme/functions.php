<?php
/**
 * Blank Theme functions and definitions
 *
 * @package Blank_Theme
 */

// 테마 버전 정의
define( 'BLANK_THEME_VERSION', '1.0.0' );

// 테마 설정
function blank_theme_setup() {
    // 제목 태그 지원
    add_theme_support( 'title-tag' );
    
    // 포스트 썸네일 지원
    add_theme_support( 'post-thumbnails' );
    
    // HTML5 지원
    add_theme_support( 'html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ) );
}
add_action( 'after_setup_theme', 'blank_theme_setup' );

// 스타일시트 등록
function blank_theme_scripts() {
    wp_enqueue_style( 'blank-theme-style', get_stylesheet_uri(), array(), BLANK_THEME_VERSION );
}
add_action( 'wp_enqueue_scripts', 'blank_theme_scripts' );