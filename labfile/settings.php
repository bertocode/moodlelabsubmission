<?php
/**
 * This file defines the admin settings for this plugin
 *
 * @package   assignsubmission_labfile
 * @author Alberto Benito Campo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


// Note: This is on by default.
$settings->add(new admin_setting_configcheckbox('assignsubmission_labfile/default',
                   new lang_string('default', 'assignsubmission_labfile'),
                   new lang_string('default_help', 'assignsubmission_labfile'), 1));

if (isset($CFG->maxbytes)) {

    $name = new lang_string('maximumsubmissionsize', 'assignsubmission_labfile');
    $description = new lang_string('configmaxbytes', 'assignsubmission_labfile');

    $maxbytes = get_config('assignsubmission_labfile', 'maxbytes');
    $element = new admin_setting_configselect('assignsubmission_labfile/maxbytes',
                                              $name,
                                              $description,
                                              1048576,
                                              get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes));
    $settings->add($element);
}

$settings->add(new admin_setting_configtext('assignsubmission_labfile/password',
                   new lang_string('password', 'assignsubmission_labfile'),
                   new lang_string('configpassword', 'assignsubmission_labfile'), ''));

				   
$settings->add(new admin_setting_configtext('assignsubmission_labfile/ipmask',
                   new lang_string('ipmask', 'assignsubmission_labfile'),
                   new lang_string('configipmask', 'assignsubmission_labfile'), '*.*.*.*'));
