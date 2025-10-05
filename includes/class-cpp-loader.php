<?php
/**
 * Hook Registration System
 * 
 * Maintains all actions and filters registered by the plugin.
 * Following the pattern from copilot-instructions.md
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Loader {
    
    /**
     * Array of actions registered with WordPress
     *
     * @var array
     */
    protected $actions;
    
    /**
     * Array of filters registered with WordPress
     *
     * @var array
     */
    protected $filters;
    
    /**
     * Initialize collections
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->actions = [];
        $this->filters = [];
    }
    
    /**
     * Add action to collection
     *
     * @param string $hook WordPress hook name
     * @param object $component Class instance
     * @param string $callback Method name
     * @param int $priority Hook priority
     * @param int $accepted_args Number of accepted arguments
     * @since 1.0.0
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * Add filter to collection
     *
     * @param string $hook WordPress hook name
     * @param object $component Class instance
     * @param string $callback Method name
     * @param int $priority Hook priority
     * @param int $accepted_args Number of accepted arguments
     * @since 1.0.0
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }
    
    /**
     * Utility function to register hook
     *
     * @param array $hooks Existing hooks collection
     * @param string $hook Hook name
     * @param object $component Class instance
     * @param string $callback Method name
     * @param int $priority Priority
     * @param int $accepted_args Arguments count
     * @return array Updated hooks collection
     * @since 1.0.0
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = [
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];
        
        return $hooks;
    }
    
    /**
     * Register all hooks with WordPress
     *
     * @since 1.0.0
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
        
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}