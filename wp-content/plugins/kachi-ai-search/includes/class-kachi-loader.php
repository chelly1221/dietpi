<?php
/**
 * 플러그인 로더 클래스
 *
 * @package Kachi_Query_System
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 액션과 필터를 등록하고 실행하는 로더 클래스
 */
class Kachi_Loader {
    
    /**
     * 등록된 액션 배열
     */
    protected $actions;
    
    /**
     * 등록된 필터 배열
     */
    protected $filters;
    
    /**
     * 생성자
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }
    
    /**
     * 액션 추가
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * 필터 추가
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * 훅 추가 헬퍼 메서드
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );
        
        return $hooks;
    }
    
    /**
     * 등록된 모든 필터와 액션 실행
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
        
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}