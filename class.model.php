<?php

/**
 * The model class
 */
class Model
{
    /**
     * @var Connection
     */
    private static $connection;
    /**
     * @var Model
     */
    private static $model;

    /**
     *Konstruktor
     */
    private function __construct()
    {
        if(self::$connection != null)
            self::$connection = new Connection();

    }

    static function getInstance()
    {
        return self::$model == null ? self::$model = new Model() : self::$model;
    }

    /**
     * Schuelerexistenz prüfen
     * @param string $vorname Schueler Vorname
     * @param string $name Schueler Nachname
     * @param string $bday Geburtsdatum
     * @return int SchuelerID
     **/
    public function checkPupilExist($vorname, $name, $bday)
    { //TODO -> $name und $vorname beinhalten auch zweit namen -> optional oder pflicht bei registierung?
        $id = null;
        $name = self::$connection->escape_string($name);
        $bday = self::$connection->escape_string($bday);
        $data = self::$connection->selectValues("SELECT id FROM schueler WHERE vorname='$vorname' AND name='$name' AND gebdatum='$bday' AND eid IS NULL");
        if (isset($data[0][0]))
            $id = $data[0][0];
        return $id;
    }

    /**
     * @param int $userid
     * @return int userType [0 - admin; 1 - parent; 2 - teacher]
     */
    public function userGetType($userid)
    {
        $data = self::$connection->selectValues("SELECT user_type FROM user WHERE id=" .$userid);

        if(!isset($data[0]))
            return null;

        return intval($data[0][0]);

    }

    /**
     * @param string $userName
     * @return int userId
     */
    public function usernameGetId($userName)
    {
        $userName = self::$connection->escape_string($userName);
        $data = self::$connection->selectValues("SELECT id FROM user WHERE username='$userName'");

        if($data == null)
            return null;

        return $data[0][0];
    }

    /**
     * @param $id int userId
     * @return string userName
     */
    public function idGetUsername($id)
    {
        $data = self::$connection->selectValues("SELECT username FROM user WHERE id=$id");

        if($data == null)
            return null;

        return $data[0][0];
    }

    /**
     * @param int $userId
     * @return string
     */
    public function parentGetName($userId)
    {
        $data = self::$connection->selectAssociativeValues("SELECT eltern.* FROM eltern, user WHERE eltern.userid=user.id AND user.id=$userId AND user.user_type=1");

        $name = $data["name"];
        $surname = $data["vorname"];

        return $surname . " " . $name; //TODO: really?

    }

    /**
     * @param int $userId
     * @return string
     */
    public function teacherGetName($userId)
    {
        $data = self::$connection->selectAssociativeValues("SELECT lehrer.* FROM lehrer, user WHERE lehrer.userid=user.id AND user.id=$userId AND user.user_type=2");

        $name = $data["name"];
        $surname = $data["vorname"];

        return $surname . " " . $name; //TODO: really?
    }

    /**
     * @param int $elternId
     * @return array[int] array[childrenId]
     */
    public function parentGetChildren($elternId)
    {
        $data = self::$connection->selectValues("SELECT * FROM schueler WHERE eid=$elternId");

        $ids = array();

        foreach ($data as $item) {
            $pid = intval($item[0]);
            array_push($ids, $pid);
        }

        return $ids;
    }

    /**
     * @param int $schuelerId
     * @return string
     */
    public function studentGetClass($schuelerId)
    {
        $data = self::$connection->selectValues("SELECT schueler.klasse FROM schueler WHERE id=$schuelerId");

        return $data[0][0];
    }

    /**
     * @param string $class
     * @return array[int] array with teacherIds
     */
    public function classGetTeachers($class)
    {
        $class = self::$connection->escape_string($class);
        $data = self::$connection->selectValues("SELECT lehrer.id FROM lehrer, unterricht WHERE unterricht.klasse='$class' AND unterricht.lid=lehrer.id"); // returns data[n][data]

        $ids = array();

        foreach ($data as $item)
        {
            $tid = intval($item[0]);
            array_push($ids, $tid);
        }

        return $ids;

    }

    /**
     * @param int $terminId
     */
    public function bookingAdd($terminId)
    {

    }

    /**
     * @param int $terminId
     */
    public function bookingDeleate($terminId)
    {

    }

    /**
     * @param $userName
     * @param $password
     * @return bool user exists in database and password is equal with the one in the database
     */
    public function passwordValidate($userName, $password)
    {
        $userName = self::$connection->escape_string($userName);
        //$password = self::$connection->escape_string($userName);

        $data = self::$connection->selectAssociativeValues("SELECT password_hash from user WHERE username='$userName'");

        if($data == null)
            return false;


        $data = $data[0];

        $pwd_hash = $data['password_hash'];


        return password_verify($password, $pwd_hash);
    }


    /**
     * @param $usr string parents username
     * @param $pid array or int parents children ids (array[int] || int)
     * @param $email string parents email
     * @param $pwd string parents password
     * @return int newly created parents id
     */
    public function registerParent($usr, $pid, $email, $pwd)
    {

        $usr = self::$connection->escape_string($usr);
        $email = self::$connection->escape_string($email);
        $pwd = password_hash($pwd, PASSWORD_DEFAULT);

        //Create parent in database and return eid
        $data = self::$connection->selectAssociativeValues("INSERT IGNORE INTO user (username, user_type, password_hash, email) VALUES ('$usr', 1,'$pwd', '$email'); SELECT LAST_INSERT_ID() as id;");

        $parentId = $data[0]['id'];

        // transform given int into array
        if(!is_array($pid))
        {
            $pid = array($pid);
        }

        // query each given pupil and set eid (one query to spare resources)
        $query = "";
        foreach ($pid as $pupilId)
        {
            $query .= "UPDATE schueler SET eid=$parentId WHERE id=$pupilId;";
        }

        self::$connection->straightQuery($query);

        //return eid
        return intval($parentId);

    }

}


?>
