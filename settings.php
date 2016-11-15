<?php

//$Id: settings.php,v 1.1.2.2 2007/12/19 17:38:41 skodak Exp $
//$settings = new admin_settingpage( 'connectslide', get_string( 'settings', 'connectslide' ) );
// Warning message if local_refined_services not installed or missing username or password
$message = '';
if (!$DB->get_record('config_plugins', array('plugin' => 'local_refinedservices', 'name' => 'version'))) {
    $message .= get_string('localrefinedservicesnotinstalled', 'connectslide') . '<br />';
}
$rs_plugin_link = new moodle_url('/admin/settings.php?section=local_refinedservices');
if (empty($CFG->connect_service_username)) {
    $message .= get_string('connectserviceusernamenotgiven', 'connectslide', array('url' => $rs_plugin_link->out())) . '<br />';
}

if (empty($CFG->connect_service_password)) {
    $message .= get_string('connectservicepasswordnotgiven', 'connectslide', array('url' => $rs_plugin_link->out())) . '<br />';
}

if (!empty($message)) {
    $caption = html_writer::tag('div', $message, array('class' => 'notifyproblem'));
    $setting = new admin_setting_heading('refined_services_warning', $caption, '<strong>' . get_string('connectsettingsrequirement', 'connectslide') . '</strong>');
    $settings->add($setting);
}

if ($hassiteconfig && !empty($CFG->connect_service_username) && !empty($CFG->connect_service_password)) {
    
    // Logo file setting.
    $name = 'mod_connectslide/slideshow_icon';
    $title = get_string('slideshowicon', 'connectslide');
    $description = get_string('slideshowicondesc', 'connectslide');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'slideshow_icon');
    $settings->add($setting);
    
    $dgoptions = array(
        0 => get_string('off', 'connectslide'),
        1 => get_string('fromadobe', 'connectslide'));
    
    $settings->add(new admin_setting_configselect('mod_connectslide/detailgrading', new lang_string('detailgradingmeeting', 'connectslide'), '', 'off', $dgoptions));
    
}


