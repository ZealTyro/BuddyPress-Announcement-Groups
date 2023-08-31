<?php
/*
Plugin Name: BuddyPress Announcement Groups
Plugin URI: https://www.zealtyro.com/plugins/announcement-groups/
Author: ZealTyro
Author URI: https://www.zealtyro.com/
Description: BuddyPress Announcement Groups by ZealTyro empowers group administrators to create announcement-only groups, where only moderators and administrators can post announcements. This helps you maintain a clear and focused communication channel.
Version: 1.0.0
*/

// Filter member value. Return false if it's an announcement group and regular member, otherwise return true
function zt_filter_group_member($is_member) {
    global $bp;
    
    if (zt_is_announcement_group(groups_get_current_group())) {
        if ($bp->is_item_admin || $bp->is_item_mod) {
            return true;
        } else {
            return false;
        }
    } else {
        return $is_member;
    }
}
add_filter('bp_group_is_member', 'zt_filter_group_member');

// Filter out group status and return 'announcement' for announcement groups
function zt_filter_group_status($status) {
    global $bp;

    if (zt_is_announcement_group(groups_get_current_group())) {
        if ($bp->is_item_admin || $bp->is_item_mod) {
            return $status;
        } else {
            return 'announcement';
        }
    } else {
        return $status;
    }
}
add_filter('bp_get_group_status', 'zt_filter_group_status');

// Create the announcement group option during group creation and editing
function zt_add_announcement_group_form() {
    ?>
    <hr>
    <div class="radio">
        <label><input type="radio" name="zt-announcement-group" value="normal" <?php zt_announcement_group_setting('normal') ?> /> This is a normal group (all group members can add content)</label>
        <label><input type="radio" name="zt-announcement-group" value="announcement" <?php zt_announcement_group_setting('announcement') ?> /> This is an announcement group (only moderators and admins can post announcements)</label>
    </div>
    <hr />
    <?php
}
add_action('bp_after_group_settings_admin', 'zt_add_announcement_group_form');
add_action('bp_after_group_settings_creation_step', 'zt_add_announcement_group_form');

// Get the announcement group setting
function zt_is_announcement_group($group = false) {
    global $groups_template;
    
    if (!$group) {
        $group =& $groups_template->group;
    }

    $group_id = isset($group->id) ? $group->id : null;

    if (!$group_id && isset($group->group_id)) {
        $group_id = $group->group_id;
    }

    $announcement_group = groups_get_groupmeta($group_id, 'zt_announcement_group');

    return ($announcement_group === 'announcement');
}

// Echo announcement group checked setting for the group admin - default to 'normal' in group creation
function zt_announcement_group_setting($setting) {
    if (zt_is_announcement_group(groups_get_current_group())) {
        echo ' checked="checked"';
    } elseif (!$announcement_group && $setting == 'normal') {
        echo ' checked="checked"';
    }
}

// Save the announcement group setting in the group meta
function zt_save_announcement_group($group) {
    global $bp, $_POST;
    if ($postval = sanitize_text_field($_POST['zt-announcement-group'])) {
        if ($postval == 'announcement') {
            groups_update_groupmeta($group->id, 'zt_announcement_group', $postval);
        } elseif ($postval == 'normal') {
            groups_delete_groupmeta($group->id, 'zt_announcement_group');
        }
    }
}
add_action('groups_group_after_save', 'zt_save_announcement_group');

// Change the name of the forum to Announcements for announcement groups
function zt_change_forum_title($args) {
    global $bp;
    if (zt_is_announcement_group(groups_get_current_group()) && bp_group_is_forum_enabled()) {
        $display = true;
        $group_permalink = bp_get_group_permalink(groups_get_current_group());

        bp_core_remove_subnav_item(bp_get_current_group_slug(), 'forum', 'groups');
        bp_core_new_subnav_item(array(
            'name'              => 'Announcements',
            'slug'              => 'forum',
            'parent_slug'       => bp_get_current_group_slug(),
            'parent_url'        => $group_permalink,
            'position'          => 10,
            'item_css_id'       => 'nav-forum',
            'screen_function'   => 'bp_template_content_display_hook',
            'user_has_access'   => $display,
            'no_access_url'     => $group_permalink,
        ), 'groups');

        if (!$bp->is_item_admin && !$bp->is_item_mod) {
            echo '<style type="text/css">#subnav a[href="#post-new"], #subnav .new-reply-link, #members-groups-li { display: none; }</style>';
        }
    }
}
add_action('bp_before_group_header', 'zt_change_forum_title');

// Close forum new topics for non-group admin users
function zt_access_topic_form($retval) {
    if ($retval == true) {
        if (zt_is_announcement_group(groups_get_current_group())) {
            $retval = zt_current_user_can_publish_announcements();
        }
    }
    return $retval;
}
add_filter('bbp_current_user_can_access_create_topic_form', 'zt_access_topic_form', 20);

// Check if user is admin in the current forum
function zt_current_user_can_publish_announcements() {
    global $bp;
    $current_group = $bp->groups->current_group->id;
    $user_id = get_current_user_id();
    
    if (groups_is_user_admin($user_id, $current_group) || groups_is_user_mod($user_id, $current_group) || current_user_can('manage_options')) {
        return true;
    } else {
        return false;
    }
}
