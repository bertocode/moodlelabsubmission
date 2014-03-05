<?php
/**
 * This file contains the definition for the library class for file submission plugin
 *
 * This class provides all the functionality for the new assign module.
 *
 * @package assignsubmission_labfile
 * @author Alberto Benito Campo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir.'/eventslib.php');
require_once('ip_in_range.php');

defined('MOODLE_INTERNAL') || die();

// File areas for file submission assignment.
define('assignsubmission_labfile_MAXFILES', 20);
define('assignsubmission_labfile_MAXSUMMARYFILES', 5);
define('assignsubmission_labfile_FILEAREA', 'submission_files');

/**
 * Library class for file submission plugin extending submission plugin base class
 *
 * @package   assignsubmission_labfile
 * @copyright Alberto Benito Campo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_labfile extends assign_submission_plugin {

    /**
     * Get the name of the file submission plugin
     * @return string
     */
    public function get_name() {
        return get_string('file', 'assignsubmission_labfile');
    }

    /**
     * Get file submission information from the database
     *
     * @param int $submissionid
     * @return mixed
     */
    private function get_file_submission($submissionid) {
        global $DB;
        return $DB->get_record('assignsubmission_labfile', array('submission'=>$submissionid));
    }

    /**
     * Get the default setting for file submission plugin
     *
     * @param MoodleQuickForm $mform The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG, $COURSE;

        $defaultmaxfilesubmissions = $this->get_config('maxfilesubmissions');
        $defaultmaxsubmissionsizebytes = $this->get_config('maxsubmissionsizebytes');
		
        $settings = array();
        $options = array();
        for ($i = 1; $i <= assignsubmission_labfile_MAXFILES; $i++) {
            $options[$i] = $i;
        }

        $name = get_string('maxfilessubmission', 'assignsubmission_labfile');
        $mform->addElement('select', 'assignsubmission_labfile_maxfiles', $name, $options);
        $mform->addHelpButton('assignsubmission_labfile_maxfiles',
                              'maxfilessubmission',
                              'assignsubmission_labfile');
        $mform->setDefault('assignsubmission_labfile_maxfiles', $defaultmaxfilesubmissions);
        $mform->disabledIf('assignsubmission_labfile_maxfiles', 'assignsubmission_labfile_enabled', 'notchecked');

        $choices = get_max_upload_sizes($CFG->maxbytes,
                                        $COURSE->maxbytes,
                                        get_config('assignsubmission_labfile', 'maxbytes'));

        $settings[] = array('type' => 'select',
                            'name' => 'maxsubmissionsizebytes',
                            'description' => get_string('maximumsubmissionsize', 'assignsubmission_labfile'),
                            'options'=> $choices,
                            'default'=> $defaultmaxsubmissionsizebytes);

        $name = get_string('maximumsubmissionsize', 'assignsubmission_labfile');
        $mform->addElement('select', 'assignsubmission_labfile_maxsizebytes', $name, $choices);
        $mform->addHelpButton('assignsubmission_labfile_maxsizebytes',
                              'maximumsubmissionsize',
                              'assignsubmission_labfile');
        $mform->setDefault('assignsubmission_labfile_maxsizebytes', $defaultmaxsubmissionsizebytes);
        $mform->disabledIf('assignsubmission_labfile_maxsizebytes',
                           'assignsubmission_labfile_enabled',
                           'notchecked');

		// Soporte para agregar contrasena
		$defaultpassword = $this->get_config('password');
		
		if (!$defaultpassword)
			$defaultpassword =  get_config('assignsubmission_labfile', 'password');
			
        $name = get_string('password', 'assignsubmission_labfile');
        $mform->addElement('text', 'assignsubmission_labfile_password', $name);
        $mform->addHelpButton('assignsubmission_labfile_password',
                              'password',
                              'assignsubmission_labfile' );
        $mform->setDefault('assignsubmission_labfile_password', $defaultpassword);
        $mform->disabledIf('assignsubmission_labfile_password',
                           'assignsubmission_labfile_enabled',
                           'notchecked');
						   
		// Soporte para agregar filtros de IP
		$default_ipmask = $this->get_config('ipmask');
		
		if (!$default_ipmask)
			$default_ipmask =  get_config('assignsubmission_labfile', 'ipmask');
			
        $name = get_string('ipmask', 'assignsubmission_labfile');
        $mform->addElement('text', 'assignsubmission_labfile_ipmask', $name);
        $mform->addHelpButton('assignsubmission_labfile_ipmask',
                              'ipmask',
                              'assignsubmission_labfile' );
        $mform->setDefault('assignsubmission_labfile_ipmask', $default_ipmask);
        $mform->disabledIf('assignsubmission_labfile_ipmask',
                           'assignsubmission_labfile_enabled',
                           'notchecked');
    }

    /**
     * Save the settings for file submission plugin
     *
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {
        $this->set_config('maxfilesubmissions', $data->assignsubmission_labfile_maxfiles);
        $this->set_config('maxsubmissionsizebytes', $data->assignsubmission_labfile_maxsizebytes);
		$this->set_config('password', $data->assignsubmission_labfile_password);
		
		// Comprobamos que la mascara de ip sea correcta
		if (!is_valid_ip_range($data->assignsubmission_labfile_ipmask))
		{
			$this->set_error( get_string('incorrect_ip_mask_format', 'assignsubmission_labfile') );
			return false;
		}
		else
			$this->set_config('ipmask', $data->assignsubmission_labfile_ipmask);

        return true;
    }

    /**
     * File format options
     *
     * @return array
     */
    private function get_file_options() {
        $fileoptions = array('subdirs'=>1,
                                'maxbytes'=>$this->get_config('maxsubmissionsizebytes'),
                                'maxfiles'=>$this->get_config('maxfilesubmissions'),
                                'accepted_types'=>'*',
                                'return_types'=>FILE_INTERNAL);
        return $fileoptions;
    }

    /**
     * Add elements to submission form
     *
     * @param mixed $submission stdClass|null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {

        if ($this->get_config('maxfilesubmissions') <= 0) {
            return false;
        }

        $fileoptions = $this->get_file_options();
        $submissionid = $submission ? $submission->id : 0;

        $data = file_prepare_standard_filemanager($data,
                                                  'files',
                                                  $fileoptions,
                                                  $this->assignment->get_context(),
                                                  'assignsubmission_labfile',
                                                  assignsubmission_labfile_FILEAREA,
                                                  $submissionid);
        $mform->addElement('filemanager', 'files_filemanager', html_writer::tag('span', $this->get_name(),
            array('class' => 'accesshide')), null, $fileoptions);

		
        $name = get_string('password', 'assignsubmission_labfile');
        $mform->addElement('password', 'upload_password', $name);
        $mform->addHelpButton('assignsubmission_labfile_password',
                              'upload_password',
                              'assignsubmission_labfile' );
        return true;
    }

    /**
     * Count the number of files
     *
     * @param int $submissionid
     * @param string $area
     * @return int
     */
    private function count_files($submissionid, $area) {

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->assignment->get_context()->id,
                                     'assignsubmission_labfile',
                                     $area,
                                     $submissionid,
                                     'id',
                                     false);

        return count($files);
    }

    /**
     * Save the files and trigger plagiarism plugin, if enabled,
     * to scan the uploaded files via events trigger
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        global $USER, $DB;

		
		// Comprobamos la ip del cliente 
		if (!ip_in_range($_SERVER['REMOTE_ADDR'], $this->get_config('ipmask')))
		{
			$this->set_error( get_string('incorrect_ip', 'assignsubmission_labfile') );
			return false;
		}
		
		// Comprobamos la contrasena que nos pasan
		if ($_POST['upload_password'] != $this->get_config('password'))
		{
			$this->set_error( get_string('incorrect_password', 'assignsubmission_labfile') );
			return false;
		}
		
        $fileoptions = $this->get_file_options();

        $data = file_postupdate_standard_filemanager($data,
                                                     'files',
                                                     $fileoptions,
                                                     $this->assignment->get_context(),
                                                     'assignsubmission_labfile',
                                                     assignsubmission_labfile_FILEAREA,
                                                     $submission->id);

        $filesubmission = $this->get_file_submission($submission->id);

        // Plagiarism code event trigger when files are uploaded.

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->assignment->get_context()->id,
                                     'assignsubmission_labfile',
                                     assignsubmission_labfile_FILEAREA,
                                     $submission->id,
                                     'id',
                                     false);

        $count = $this->count_files($submission->id, assignsubmission_labfile_FILEAREA);

        // Send files to event system.
        // This lets Moodle know that an assessable file was uploaded (eg for plagiarism detection).
        $eventdata = new stdClass();
        $eventdata->modulename = 'assign';
        $eventdata->cmid = $this->assignment->get_course_module()->id;
        $eventdata->itemid = $submission->id;
        $eventdata->courseid = $this->assignment->get_course()->id;
        $eventdata->userid = $USER->id;
        if ($count > 1) {
            $eventdata->files = $files;
        }
        $eventdata->file = $files;
        $eventdata->pathnamehashes = array_keys($files);
        events_trigger('assessable_file_uploaded', $eventdata);

        if ($filesubmission) {
            $filesubmission->numfiles = $this->count_files($submission->id,
                                                           assignsubmission_labfile_FILEAREA);
            return $DB->update_record('assignsubmission_labfile', $filesubmission);
        } else {
            $filesubmission = new stdClass();
            $filesubmission->numfiles = $this->count_files($submission->id,
                                                           assignsubmission_labfile_FILEAREA);
            $filesubmission->submission = $submission->id;
            $filesubmission->assignment = $this->assignment->get_instance()->id;
            return $DB->insert_record('assignsubmission_labfile', $filesubmission) > 0;
        }
    }

    /**
     * Produce a list of files suitable for export that represent this feedback or submission
     *
     * @param stdClass $submission The submission
     * @return array - return an array of files indexed by filename
     */
    public function get_files(stdClass $submission, stdClass $user) {
        $result = array();
        $fs = get_file_storage();

        $files = $fs->get_area_files($this->assignment->get_context()->id,
                                     'assignsubmission_labfile',
                                     assignsubmission_labfile_FILEAREA,
                                     $submission->id,
                                     'timemodified',
                                     false);

        foreach ($files as $file) {
            $result[$file->get_filename()] = $file;
        }
        return $result;
    }

    /**
     * Display the list of files  in the submission status table
     *
     * @param stdClass $submission
     * @param bool $showviewlink Set this to true if the list of files is long
     * @return string
     */
    public function view_summary(stdClass $submission, & $showviewlink) {
        $count = $this->count_files($submission->id, assignsubmission_labfile_FILEAREA);

        // Show we show a link to view all files for this plugin?
        $showviewlink = $count > assignsubmission_labfile_MAXSUMMARYFILES;
        if ($count <= assignsubmission_labfile_MAXSUMMARYFILES) {
            return $this->assignment->render_area_files('assignsubmission_labfile',
                                                        assignsubmission_labfile_FILEAREA,
                                                        $submission->id);
        } else {
            return get_string('countfiles', 'assignsubmission_labfile', $count);
        }
    }

    /**
     * No full submission view - the summary contains the list of files and that is the whole submission
     *
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission) {
        return $this->assignment->render_area_files('assignsubmission_labfile',
                                                    assignsubmission_labfile_FILEAREA,
                                                    $submission->id);
    }

    /**
     * Return true if this plugin can upgrade an old Moodle 2.2 assignment of this type
     * and version.
     *
     * @param string $type
     * @param int $version
     * @return bool True if upgrade is possible
     */
    public function can_upgrade($type, $version) {

        $uploadsingletype ='uploadsingle';
        $uploadtype ='upload';

        if (($type == $uploadsingletype || $type == $uploadtype) && $version >= 2011112900) {
            return true;
        }
        return false;
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        // Will throw exception on failure.
        $DB->delete_records('assignsubmission_labfile',
                            array('assignment'=>$this->assignment->get_instance()->id));

        return true;
    }

    /**
     * Formatting for log info
     *
     * @param stdClass $submission The submission
     * @return string
     */
    public function format_for_log(stdClass $submission) {
        // Format the info for each submission plugin (will be added to log).
        $filecount = $this->count_files($submission->id, assignsubmission_labfile_FILEAREA);

        return get_string('numfilesforlog', 'assignsubmission_labfile', $filecount);
    }

    /**
     * Return true if there are no submission files
     * @param stdClass $submission
     */
    public function is_empty(stdClass $submission) {
        return $this->count_files($submission->id, assignsubmission_labfile_FILEAREA) == 0;
    }

    /**
     * Get file areas returns a list of areas this plugin stores files
     * @return array - An array of fileareas (keys) and descriptions (values)
     */
    public function get_file_areas() {
        return array(assignsubmission_labfile_FILEAREA=>$this->get_name());
    }

    /**
     * Copy the student's submission from a previous submission. Used when a student opts to base their resubmission
     * on the last submission.
     * @param stdClass $sourcesubmission
     * @param stdClass $destsubmission
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission) {
        global $DB;

        // Copy the files across.
        $contextid = $this->assignment->get_context()->id;
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid,
                                     'assignsubmission_labfile',
                                     assignsubmission_labfile_FILEAREA,
                                     $sourcesubmission->id,
                                     'id',
                                     false);
        foreach ($files as $file) {
            $fieldupdates = array('itemid' => $destsubmission->id);
            $fs->create_file_from_storedfile($fieldupdates, $file);
        }

        // Copy the assignsubmission_labfile record.
        if ($filesubmission = $this->get_file_submission($sourcesubmission->id)) {
            unset($filesubmission->id);
            $filesubmission->submission = $destsubmission->id;
            $DB->insert_record('assignsubmission_labfile', $filesubmission);
        }
        return true;
    }
}

