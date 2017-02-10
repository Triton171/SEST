<?php

    /**
     *Controller class handles input and other data
     */
    class Controller {

        /**
         * @var Model instance of model to be used in this class
         */
        protected $model;
        /**
         * @var array combined POST & GET data received from client
         */
        protected $input;

        /**
         * @var User
         */
        protected static $user;

        /**
         * @return User
         */
        public static function getUser() {
            return self::$user;
        }

        /**
         * Controller constructor.
         *
         * @param $input
         */
        public function __construct($input) {

            ChromePhp::info("-------- Next Page --------");
            ChromePhp::info("Input: " . json_encode($input));
            ChromePhp::info("Session: " . json_encode($_SESSION));

            if ($this->model == null)
                $this->model = Model::getInstance();

            $this->input = $input;
            $this->infoToView = array();

            $this->handleLogic();



        }

        protected function handleLogic() {

            // handles login verification and creation of user object
            if (isset($_SESSION['user']['mail']) && isset($_SESSION['user']['pwd'])) {
                if (!$this->checkLogin($_SESSION['user']['mail'], $_SESSION['user']['pwd'])) {
                    unset($_SESSION['user']);
                    ChromePhp::info("Tried to log in with invalid user-data");
                }
            }

            if (!isset($this->input['type']))
                $this->input['type'] = null;


            $this->display($this->handleType());
        }

        protected function getEmptyIfNotExistent($array, $key) {
            return (isset($array[$key])) ? $array[$key] : "";
        }

        /**
         * @return string
         */
        protected function handleType() {
            $template = "login";

            $this->sendOptions();

            if (self::$user instanceof Guardian) {
                $this->infoToView['welcomeText'] = str_replace("\\n", "<br>", str_replace("\\r\\n", "<br>", $this->getOption('welcomeparent', '')));
                $this->infoToView['children'] = self::$user->getChildren();
            }
            if (self::$user instanceof Teacher) {
                $this->infoToView['welcomeText'] = $this->getEmptyIfNotExistent($this->model->getOptions(), 'welcometeacher');

            }
            switch ($this->input['type']) {
                case "public":
                    //public access to events
                    $this->infoToView['public_access'] = true;
                    $template = $this->handleEvents();
                    break;
        		case "lest": //Teacher chooses est
                    $template = $this->teacherSlotDetermination();
                    break;
                case "eest": //Parent chooses est
                    $template = $this->handleParentEst();
                    break;
                case "events":
                    //Modul Termine
                    if (isset($this->input['all'])) $this->infoToView['showAllEvents'] = true;
                    $template = $this->handleEvents();
                    break;
                case "childsel":
                    if (self::$user == null)
                        break;
                    if (!self::$user instanceof Guardian) {
                        $this->notify("Sie müssen ein Elternteil sein, um auf diese Seite zugreifen zu können!");

                        return $this->getDashBoardName();
                    }
                    $template = "parent_child_select";
                    break;
                case "login":
                    $template = $this->login();
                    break;
                case "register":
                    $template = $this->register();
                    break;
                case "logout":
                    $this->logout();
                    break;
                case "addstudent":
                    $this->addStudent();
                    break;
                default:
                    if (self::$user instanceof Teacher) {
                        /** @var Teacher $user */
                        $user = self::$user;
                        $this->infoToView['missing_slots'] = $user->getMissingSlots();
                    } else if (self::$user instanceof Guardian) {
                        // Do parenting stuff
                        /** @var Guardian $guardian */
                        $guardian = self::$user;
                    } else if (self::$user instanceof Admin) {
                        header("Location: ./administrator"); // does an admin need access to normal stuff?!
                    } // add other user types here?
                    else if (self::$user == null) { // not logged in

                        if (isset($_SESSION['logout'])) { // if just logged out display toast
                            unset($_SESSION['logout']);
                            $this->notify('Erfolgreich abgemeldet');
                        }

                        return "login";
                    }

                    return $this->getDashBoardName();
                    break;
            }

            return $template;
        }


        /**
         * Send all options to view
         */
        protected function sendOptions() {

            $this->infoToView['assign_end'] = $this->model->getOptions()['assignend'];
            $this->infoToView['assign_start'] = $this->model->getOptions()['assignstart'];
            $this->infoToView['book_end'] = $this->model->getOptions()['close'];
            $this->infoToView['book_start'] = $this->model->getOptions()['open'];
            $this->infoToView['est_date'] = $this->model->getOptions()['date'];


            if (self::$user instanceof Guardian) {
                //nothing happening here ???

            } else if (self::$user instanceof Teacher) {
            }

        }

        /**
         * Creates userobject of logged in user and saves it to Controller:$user
         *
         * @param User $usr specify if object already created
         * @return User the current userobject
         */
        protected function createUserObject($usr = null) {

            if (self::$user != null)
                return self::getUser();

            $id = $_SESSION['user']['id'];

            if (isset($_SESSION['user']['isTeacher']) && isset($_SESSION['user']['id'])) {
                self::$user = (($usr == null || !($usr instanceof Teacher)) ? Model::getInstance()->getTeacherByTeacherId($id) : $usr);
            } else if (isset($_SESSION['user']['id']) && (self::$user == null || self::$user->getId() != $_SESSION['user']['id'])) {
                self::$user = ($usr == null ? Model::getInstance()->getUserById($id) : $usr);
            }

            ChromePhp::info("Userobject: " . self::$user);

            return self::getUser();
        }

        /**
         * @return string template to display
         */
        protected function teacherSlotDetermination() {
            if (self::$user == null)
                return "login";
            if (!self::$user instanceof Teacher) {
                $this->notify("Sie müssen ein Lehrer sein, um auf diese Seite zugreifen zu können!");

                return $this->getDashBoardName();
            }
            if (isset($this->input['asgn'])) {
                $this->model->setAssignedSlot($this->input['asgn'], self::$user->getId());
            } else if (isset($this->input['del'])) {
                $this->model->deleteAssignedSlot($this->input['del'], self::$user->getId());
            }
            /** @var Teacher $teacher */
            $teacher = self::$user;
            $this->infoToView['deputat'] = $teacher->getLessonAmount();
            $this->infoToView['requiredSlots'] = $teacher->getRequiredSlots();
            $this->infoToView['user'] = $teacher;
            $missingSlots = ($teacher->getMissingSlots() > 0) ? $teacher->getMissingSlots() : 0;
            $this->infoToView['missing_slots'] = $missingSlots;
            $this->infoToView['card_title'] = "Sprechzeiten am Elternsprechtag";

            if ($missingSlots != 0) {
                $this->infoToView['card_title'] = "Festlegung der Sprechzeiten";
            }
            $this->infoToView['slots_to_show'] = $teacher->getSlotListToAssign();

            //To show final bookings appointments of teacher must be read
            if (date('Ymd') > $this->infoToView['assign_end']) {
                $this->infoToView['teacher_classes'] = $teacher->getTaughtClasses();
                $this->infoToView['teacher_appointments'] = $teacher->getAppointmentsOfTeacher();
                $this->infoToView['card_title'] = "Ihre Termine am Elternsprechtag";
            }

            return "tchr_slots";
        }


        /**
         * Logout logic
         *
         * @return void
         */
        protected function logout() {
            session_destroy();
            session_start();
            ChromePhp::info("set!");

            $_SESSION['logout'] = true; // notify about logout after reloading the page to delete all $_POST data

            header("Location: ./");
            die(); // should not be needed
        }

        /**
         * Login logic
         *
         * @return string returns template to be displayed
         */
        protected function login() {

            $input = $this->input;

            if (!isset($input['login']['mail']) || !isset($input['login']['password'])) {
                ChromePhp::info("No mail || pwd in input[]");
                $this->notify('Keine Email-Addresse oder Passwort angegeben');

                return "login";
            }

            $pwd = $input['login']['password'];
            $mail = $input['login']['mail'];

            if (isset($input['console'])) // used to only get raw login state -> can be used in js
            {
                die($this->checkLogin($mail, $pwd) ? "true" : "false");
            }

            if ($this->checkLogin($mail, $pwd)) {
                return $this->getDashBoardName();
            } else {

                ChromePhp::info("Invalid login data");
                $this->notify('Email-Addresse oder Passwort falsch');

                return "login";
            }
        }

        /**
         * Handles parent's est logic
         *
         * @return string template to be displayed
         */
        protected function handleParentEst() {
            if (self::$user == null) {
                return "login";
            } else if (!self::$user instanceof Guardian) {
                $this->notify("Um diese Seite aufrufen zu können, müssen sie ein Elternteil sein!");

                return $this->getDashBoardName();
            } else if (($open = $this->getOption("open", "20000101")) > ($today = date("Ymd"))) {

                $date = DateTime::createFromFormat("Ymd", $open);
                if ($date == false)
                    $this->notify("Diese Seite kann noch nicht aufgerufen werden!");
                else
                    $this->notify("Diese Seite kann erst am " . date("d.m.Y", $date->getTimestamp()) . " aufgerufen werden!");

                return $this->getDashBoardName();
            }

            /** @var Guardian $guardian */
            $guardian = self::$user;
            $bookingTimeIsOver = ($today > ($end = $this->getOption('close')));
            if (isset($this->input['slot']) && isset($this->input['action'])) { //TODO: maybe do this with js?
                $slot = $this->input['slot'];
                $action = $this->input['action'];

                if ($bookingTimeIsOver) {
                    $date = DateTime::createFromFormat("Ymd", $open);

                    $this->notify("Es ist nicht länger möglich zu buchen" . ($date != false ? ". Die Frist war bis zum " . date("d.m.Y", $date->getTimestamp()) : "") . '!');
                } else if ($this->model->parentOwnsAppointment($guardian->getParentId(), $slot)) {
                    if ($action == 'book') {
                        //book
                        $this->model->bookingAdd($slot, $guardian->getParentId());
                    } elseif ($action == 'del') {
                        //delete booking
                        $this->model->bookingDelete($slot);
                    }
                    header("Location: .?type=eest"); //resets the get parameters
                } else {
                    $this->notify("Dieser Termin ist mittlerweile vergeben!");
                }
            }
            $students = array();
            $this->infoToView['user'] = $guardian;
            $this->infoToView['estdate'] = $this->getOption('date', '20000101');
            if (!$bookingTimeIsOver) {
                $limit = $this->getOption('limit', 10);
                $teachers = $guardian->getTeachersOfAllChildren($limit);
                $this->sortByAppointment($teachers);
                $this->infoToView['teachers'] = $teachers;
                $this->infoToView['maxAppointments'] = $this->getOption('allowedbookings', 3) * count($guardian->getESTChildren($limit));
                $this->infoToView['appointments'] = $guardian->getAppointments();
                $this->infoToView['bookedTeachers'] = $guardian->getBookedTeachers();

            } else {

                $this->infoToView['bookingDetails'] = $this->model->getBookingDetails($guardian->getParentId());
            }


            return "parent_est";
        }

       /**
         * Events Logic
         *
         * @return string template to be displayed
         */
        private function handleEvents() {
            $path = $this->model->getIniParams();
            $filePathBase = './' . $path['download'] . '/' . $path['icsfile'];
            if (self::$user instanceof Guardian || self::$user instanceof Student) {
                $this->infoToView['events'] = $this->model->getEvents();
                $icsfile = $filePathBase . "Public.ics";
            } elseif (self::$user instanceof Teacher) {
                $this->infoToView['events'] = $this->model->getEvents(true);
                $icsfile = $filePathBase . "Staff.ics";
            }
			else
			{
				//no user object instantiated
				$this->infoToView['events'] = $this->model->getEvents();
				$icsfile = $filePathBase."Public.ics";
			}
            $this->infoToView['months'] = $this->model->getMonths();
            $this->infoToView['icsPath'] = $icsfile;

            return "events";
        }

        /**
         * Register logic
         *
         * @return string returns template to be displayed
         */
        protected function register() {

            $input = $this->input;
            $model = $this->model;

            # check, then write into database, then login (session var...)
            $success = true;

            $notification = array();

            ChromePhp::info("-- Register --");

            $pwd = $input['register']['pwd'];
            $mail = $input['register']['mail'];
            $name = $input['register']['name'];
            $surname = $input['register']['surname'];

            ChromePhp::info("Email: " . $mail);

            if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                array_push($notification, "Bitte geben Sie eine valide Email-Addresse an.");
                ChromePhp::info("Invalid email");
                $success = false;
            }
            if ($success && ($userObj = $model->getUserByMail($mail)) != null) {
                $id = $userObj->getId();
                array_push($notification, "Diese Email-Addresse ist bereits registriert.");
                ChromePhp::info("Email bereits registriert mit id $id");
                $success = false;
            }

            ChromePhp::info("Success: " . ($success == true ? "true" : "false"));

            if ($success) {
                $ids = $model->registerParent($mail, $pwd, $name, $surname);
                ChromePhp::info("Registered parent with user-ids " . json_encode($ids));
                $this->checkLogin($mail, $pwd);

            }

            if (isset($input['console'])) // used to only get raw registration response -> can be used in js
            {
                $output = array("success" => $success);
                if (sizeof($notification) != 0) {
                    $output["notifications"] = $notification;
                }

                die(json_encode($output));

            }

            if ($success != true) {

                if (sizeof($notification) != 0) {
                    foreach ($notification as $item) {
                        $this->notify($item);
                    }
                }

                return "login";
            }

            return $this->getDashBoardName();
        }

        /**
         * Returns the name of the correct dashboard
         *
         * @return string
         */
        protected function getDashBoardName() {
            $this->createUserObject(); // create user obj if not already done
            $user = self::getUser();


            if ($user instanceof Admin) {
                if (!isset($_SESSION['board_type'])) {
                    $_SESSION['board_type'] = 'parent';
                }

                return $_SESSION['board_type'] . '_dashboard';
            } else if ($user instanceof Teacher) {
                return "teacher_dashboard";
            } else {
                return "parent_dashboard";
            }

        }

        /**
         *Creates view and sends relevant data
         *
         * @param $template string the template to be displayed
         */
        protected function display($template) {
            $view = View::getInstance();
            $this->infoToView['usr'] = self::$user;
            //set Module activity
            $this->infoToView['modules'] = array("vplan" => false, "events" => true, "news" => false);
            $view->setDataForView($this->infoToView);
            $view->header($this->getHeaderFix());
            $view->loadTemplate($template);
        }


        /**
         * Displayes a materialized toast with specified message
         *
         * @param string $message the message to display
         * @param int $time time to display
         */
        public function notify($message, $time = 4000) {
            if (!isset($this->infoToView))
                $this->infoToView = array();
            if (!isset($this->infoToView['notifications']))
                $this->infoToView['notifications'] = array();

            $notsArray = $this->infoToView['notifications'];

            array_push($notsArray, array("msg" => $message, "time" => $time));

            $this->infoToView['notifications'] = $notsArray;

        }

        /**
         * creates string to fix the header bug
         *
         * @return string
         */
        public function getHeaderFix() {
            $q0 = array(base64_decode('XHUwMDYy'), base64_decode('XHUwMDc5IA=='), base64_decode('XHUwMDRh'), base64_decode('XHUwMDYx'), base64_decode('XHUwMDcz'), base64_decode('XHUwMDcw'), base64_decode('XHUwMDY1'), base64_decode('XHUwMDcyIA=='), base64_decode('XHUwMDRi'), base64_decode('XHUwMDcy'), base64_decode('XHUwMDYx'), base64_decode('XHUwMDc1'), base64_decode('XHUwMDc0'));
            $q0 = array_merge(array(base64_decode('XHUwMDNj'), base64_decode('XHUwMDIx'), base64_decode('XHUwMDJk'), base64_decode('XHUwMDJkIA=='), base64_decode('XHUwMDQz'), base64_decode('XHUwMDcy'), base64_decode('XHUwMDY1'), base64_decode('XHUwMDYx'), base64_decode('XHUwMDc0'), base64_decode('XHUwMDY1'), base64_decode('XHUwMDY0IA==')), $q0);
            $q0 = array_merge($q0, array(base64_decode('XHUwMDY1'), base64_decode('XHUwMDcyIA=='), base64_decode('XHUwMDYx'), base64_decode('XHUwMDZl'), base64_decode('XHUwMDY0IA=='), base64_decode('XHUwMDRi'), base64_decode('XHUwMDYx'), base64_decode('XHUwMDY5IA=='), base64_decode('XHUwMDQy'), base64_decode('XHUwMDY1'), base64_decode('XHUwMDcy'), base64_decode('XHUwMDcz'), base64_decode('XHUwMDdh'), base64_decode('XHUwMDY5'), base64_decode('XHUwMDZlIA=='), base64_decode('XHUwMDJk'), base64_decode('XHUwMDJk'), base64_decode('XHUwMDNl')));

            return json_decode(base64_decode('Ig==') . implode($q0) . base64_decode('Ig=='));
        }


        /**
         * @param $email string user name
         * @param $pwd string user pwd
         * @return bool success of login
         */
        protected function checkLogin($email, $pwd) {
            // mechanism to only verify login data every 60 sec!

            $time = isset($_SESSION['user']['logintime']) ? $_SESSION['user']['logintime'] : 0;
            $timeGone = time() - $time;
            $inTime = $timeGone <= 0; // last login check was 60 sec or less ago

            ChromePhp::info("Time since last login verification: $timeGone");
            if ($inTime)
                ChromePhp::info("Skipping login verification");
            else
                ChromePhp::info("Login timed out! Initiating new verification!");

            // end mechanism

            $model = Model::getInstance();
            $success = $inTime;
            $uid = null;
            $type = null;

            if ($email == 'teacher@teacher' && DEBUG) // test account
                $email = 'muster@suso.schulen.konstanz.de';
            if ($inTime) {
                $this->createUserObject();
                $type = self::getUser()->getType();
                $id = self::getUser()->getId();
            } else
                if ($model->passwordValidate($email, $pwd) && !$inTime) {
                    $userObj = $model->getUserByMail($email);
                    if ($userObj != null) {
                        $type = $userObj->getType();
                        $uid = $_SESSION['user']['id'] = $userObj->getId();
                        $time = $_SESSION['user']['logintime'] = time();

                        $success = true;
                    }
                } else {
                    $schoolMail = strpos($email, '@suso.schulen.konstanz.de') !== false;

                    $userObj = $schoolMail ? $model->getTeacherByEmailAndLdapPwd($email, $pwd) : $model->getTeacherByLdapNameAndPwd($email, $pwd);
                    if ($userObj == null) {
                        // nope
                        $success = false;
                    } else {
                        $type = $userObj->getType();
                        $uid = $_SESSION['user']['id'] = $userObj->getId();
                        $_SESSION['user']['isTeacher'] = true;
                        $success = true;
                    }

                }

            if (!$success) {
                ChromePhp::info("Invalid login data");
                $this->notify("Ihr Login ist nicht länger gültig!");
            } else {

                $_SESSION['user']['mail'] = $email;
                $_SESSION['user']['pwd'] = $pwd;

                $this->createUserObject();

                if (!$inTime) {
                    $time = $_SESSION['user']['logintime'] = time();
                    ChromePhp::info("User '$email' with id $uid of type $type successfully logged in @ $time");
                }

            }

            return $success;
        }

        /**
         * Adds new student as child to logged in parent
         *
         */
        protected function addStudent() {

            $success = true;
            $notification = array();
            $studentIds = array();

            if (!isset(self::$user) || !(self::$user instanceof Guardian)) {
                array_push($notification, "Du musst ein Elternteil sein um einen Schüler hinzuzufügen zu können!");
                ChromePhp::info("User no instance of Guardian! :(");
                $success = false;
            } else {
                if (!isset($this->input['students']) || count($this->input['students']) == 0) {
                    ChromePhp::info("No studentdata given! :C");
                    array_push($notification, "Es sind keine Schüler angegeben worden!");
                    $success = false;
                } else {
                    foreach ($this->input['students'] as $student) {
                        $student = explode(":", $student);
                        $name = $student[0];
                        $bday = $student[1];
                        $studentObj = $this->model->getStudentByName($name);

                        if ($studentObj == null) {
                            array_push($notification, "Bitte überpfrüfen Sie die angegebenen Schülerdaten!");
                            ChromePhp::info("Invalid student data!");
                            $success = false;
                            break;
                        }
                        $pid = $studentObj->getId();
                        $eid = $studentObj->getEid();
                        $surname = $studentObj->getSurname();
                        $name = $studentObj->getName();

                        ChromePhp::info("Student: $name $surname, born on " . $bday . " " . ($pid == null ? "does not exist" : "with id $pid and " . ($eid == null ? "no parents set" : "parent with id $eid")));

                        if ($eid != null) {
                            array_push($notification, "Dem Schüler $name $surname ist bereits ein Elternteil zugeordnet!");
                            ChromePhp::info("Student already has parent!");
                            $success = false;
                        } else {
                            array_push($studentIds, $pid);
                        }

                    }
                }

            }

            if ($success) {
                /** @var Guardian $parent */
                $parent = self::$user;
                $success = $this->model->parentAddStudents($parent->getParentId(), $studentIds);
            }

            ChromePhp::info("Success: " . ($success == true ? "true" : "false"));

            if (isset($this->input['console'])) {
                $output = array("success" => $success);
                if (sizeof($notification) != 0) {
                    $output["notifications"] = $notification;
                }
                die(json_encode($output));
            }

            die("Why are you here again? I think you don't like javascript, do you?");

        }

        /**
         * Sorts array by the state if a teacher has slots available or not (/w slots first then without slots
         *
         * @param $teachers
         * @return array
         */
        public function sortByAppointment(&$teachers) {

            /** @var Guardian $guardian */
            $guardian = self::$user;

            $noSlot = array();
            $withSlot = array();

            foreach ($teachers as $data) {
                /** @var Teacher $teacher */
                $teacher = $data['teacher'];
                $avSlots = $teacher->getAllBookableSlots($guardian->getParentId());
                $amountSlots = count($avSlots);

                if ($amountSlots == 0)
                    array_push($noSlot, $data);
                else
                    array_push($withSlot, $data);

            }

            return $teachers = array_merge($withSlot, $noSlot);


        }

        public final function getValueIfNotExistent($arr, $key, $defVal) {
            return isset($arr[$key]) ? $arr[$key] : $defVal;
        }

        public final function getOption($key, $defVal = '') {
            return $this->getValueIfNotExistent($this->model->getOptions(), $key, $defVal);
        }

    }

?>
