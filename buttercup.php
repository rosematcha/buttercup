<?php
/**
 * Plugin Name:       Buttercup
 * Description:       Custom blocks for Reese's sites.
 * Version:           1.0.0
 * Author:            Reese Lundquist
 * Text Domain:       buttercup
 */

if (!defined('ABSPATH')) {
    exit;
}

function buttercup_blocks_init()
{
    register_block_type(__DIR__ . '/build/team');
    register_block_type(__DIR__ . '/build/team-member');
}
add_action('init', 'buttercup_blocks_init');
