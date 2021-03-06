<?php

/**
 * User: Joel Häberli
 * Date: 17.03.2017
 * Time: 08:44
 */

define("PICTURENAME_IN_FILES_ARRAY", "picture");
define("THUMBNAIL_SIZE", 324);

class Picture extends Model {
    
    private $id;
    private $tag;
    private $tags;
    private $title;
    private $picture_blob;
    private $thumbnail_blob;
    private $picture_ploc; // What is ploc? -> Picture Location
    private $thumbnail_ploc; // Same
    
    private $picture;
    private $thumbnail;
    
    private $galleryId;
    
    const GET_PICTURE_BLOB_BY_ID = "SELECT id, tag, title, picture_blob, thumbnail_blob FROM pic WHERE id = :id;";
    const GET_PICTURES_BLOB_BY_GALLERY_ID = "SELECT P.id, P.tag, P.title, P.picture_blob, P.thumbnail_blob FROM pic AS P INNER JOIN gallery_pic AS GP ON P.id = GP.pic_id INNER JOIN gallery AS G ON G.id = GP.gallery_id WHERE G.id = :idGallery;";
    const GET_X_PICTURES_BLOB = "SELECT id, tag, title, picture_blob, picture_thumbnail FROM pic ORDER BY id DESC LIMIT :num;";
    const GET_LAST_CREATED_PICTURE_ID_FOR_GALLERY_CONSTRAINT = "SELECT id from pic ORDER BY id DESC LIMIT 1";
    const GET_TAGS_OF_PICTURE = "SELECT T.tag_name, T.tag_id FROM pic AS P INNER JOIN tag_pic AS TP ON P.id = TP.pic_id INNER JOIN tag AS T ON T.tag_id = TP.tag_id WHERE P.id = :pid;";
    
    const ADD_PICTURE = "INSERT INTO pic (tag, title, picture_blob, thumbnail_blob) VALUES (:tag, :title, :picture_blob, :thumbnail_blob);";
    const ADD_GALLERY_CONSTRAINT = "INSERT INTO gallery_pic (gallery_id, pic_id) VALUES (:galleryId, :picId)";
    
    const UPDATE_TAG = "UPDATE gallery SET tag = :tag WHERE id = :id;";
    const UPDATE_TITLE = "UPDATE gallery SET title = :title WHERE id = :id;";
    
    const DELETE_GALLERY_BY_ID = "DELETE FROM gallery WHERE id = :id";
    
    const DELETE_PICTURE_FROM_GALLERY = "DELETE FROM gallery_pic WHERE pic_id = :picture";
    const DELETE_PICTURE = "DELETE FROM pic WHERE id = :picture";
    
    public function __construct($galleryId = null, $id = null, $tag = null, $title = null) {
        
        $this->galleryId = $galleryId;
        $this->id = $id;
        $this->tag = $tag;
        $this->title = $title;
    }
    
    public static function addPicture($galleryId, $tag, $title) {
        
        //Bild hinzufügen
        self::setQueryParameter(array('tag'=>$tag,'title'=>$title,'picture_blob'=>self::picToBlob(PICTURENAME_IN_FILES_ARRAY),'thumbnail_blob'=>self::createThumbnailBlob(PICTURENAME_IN_FILES_ARRAY)));
        self::modelInsert(self::ADD_PICTURE_STATEMENT);
        
        //Bild verbinden mit Galerie
        $newPicId = self::modelSelect(self::GET_LAST_CREATED_PICTURE_ID_FOR_GALLERY_CONSTRAINT_STATEMENT);
        self::setQueryParameter(array('galleryId' => $galleryId, 'picId' => $newPicId));
        self::modelInsert(self::ADD_GALLERY_CONSTRAINT_STATEMENT);
        
        //Tags mit Bild verbinden
        $tagArray = explode(";", $tag);
        foreach ($tagArray as $tag) {
            $tag = trim($tag);
            if (Tag::tagExists($tag)) {
                $t = Tag::getTagByName($tag);
                Tag::setPictureTagConstraint($t->getId(), $newPicId);
            } else {
                $t = Tag::create($tag);
                Tag::setPictureTagConstraint($t->getId(), $newPicId);
            }
        }
    }
    
    public function addTag($tagName) {
        
        if (Tag::tagExists($tagName)) {
            $t = Tag::getTagByName($tagName);
            Tag::setPictureTagConstraint($t->getId(), $this->getId());
        } else {
            $t = Tag::create($tagName);
            Tag::setPictureTagConstraint($t->getId(), $this->getId());
        }
    }
    
    public function removeTag($tagName) {
        if(in_array($tagName,$this->getTags())) {
            $t = Tag::getTagByName($tagName);
            Tag::removePictureTagConstraint($t->getId(), $this->getId());
            unset($this->tags[array_search($tagName, $this->tags)]);
        } else {
            return NULL;
        }
    }
    
    public function hasUserAccess($email)
    {
        if (!$this->getId())
            return NULL;
        self::setQueryParameter(['pid' => $this->getId()]);
        $owner = self::modelSelect(self::HAS_USER_ACCESS_STATEMENT);
        if ($owner)
            return $owner == $email;
        return false;
    }
    
    public static function updatePicture($id, $tag = null, $title = null) {
        
        if (isset($tag)) {
            self::setQueryParameter(array('id' => $id, 'tag' => $tag));
            self::modelUpdate(self::UPDATE_TAG_STATEMENT);
        }
        if (isset($title)) {
            self::setQueryParameter(array('id' => $id, 'title' => $title));
            self::modelUpdate(self::UPDATE_TITLE_STATEMENT);
        }
    }
    
    public function getGallery() : Gallery
    {
        if (!$this->getId())
            return NULL;
        
        self::setQueryParameter(['pid' => $this->getId()]);
        return self::modelSelect(self::GET_GALLERY_STATEMENT);
    }
    
    public static function getPictureById($id) : Picture{
        
        self::setQueryParameter(array('id' => $id));
        $res = self::modelSelect(self::GET_PICTURES_BLOB_BY_ID_STATEMENT);
        return $res;
        
    }
    
    public static function getPicturesFromGallery($idGallery) {
        
        self::setQueryParameter(array('idGallery' => $idGallery));
        return self::modelSelect(self::GET_PICTURES_BLOB_BY_GALLERY_ID_STATEMENT);
    }
    
    public static function getNumberOfPictures($number) {
        
        self::setQueryParameter(array('num' => $number));
        self::modelSelect(self::GET_X_PICTURES_BLOB_STATEMENT);
    }
    
    public static function deletePictureById($id) {
    
        self::setQueryParameter(array('picture' => $id));
        self::modelDelete(self::DELETE_PICTURE_FROM_GALLERY_STATEMENT);
        self::modelDelete(self::DELETE_PICTURE_STATEMENT);
    }
    
    public static function picToBlob($nameInFilesArray) {
        
        $tmp = $_FILES[$nameInFilesArray]['tmp_name'];
        return file_get_contents($tmp);
    }
    
    public static function blobToPic($blob) {
        return '<img src="data:image/jpg;base64,' .  base64_encode($blob)  . '" />';
    }
    
    public static function createThumbnailBlob($nameInFilesArray) {
    
        $image = imagecreatefromstring(self::picToBlob($nameInFilesArray));
    
        $tempName = bin2hex(random_bytes(8)) . '.pic';
    
        imagepng($image, $tempName);
    
        list($width, $height) = getimagesize($tempName);
    
        $imgratio=$width/$height;
    
        //Ist das Bild höher als breit?
        if($imgratio>1)
        {
            $newwidth = THUMBNAIL_SIZE;
            $newheight = THUMBNAIL_SIZE/$imgratio;
        }
        else
        {
            $newheight = THUMBNAIL_SIZE;
            $newwidth = THUMBNAIL_SIZE*$imgratio;
        }
    
        $thumb = imagecreatetruecolor ($newwidth,$newheight);
    
        imagecopyresized($thumb, $image, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
    
        unlink($tempName);
        
        ob_end_clean();
        ob_start();
        imagepng($thumb);
        $contents = ob_get_contents();
        ob_end_clean();
        ob_start();
        imagedestroy($image);
    
        return $contents;
    }
    
    const GET_PICTURES_BLOB_BY_ID_STATEMENT = 1;
    const GET_PICTURES_BLOB_BY_GALLERY_ID_STATEMENT = 2;
    const GET_X_PICTURES_BLOB_STATEMENT = 3;
    const GET_LAST_CREATED_PICTURE_ID_FOR_GALLERY_CONSTRAINT_STATEMENT = 4;
    const GET_TAGS_OF_PICTURE_STATEMENT = 5;
    const HAS_USER_ACCESS_STATEMENT = 6;
    const GET_GALLERY_STATEMENT = 7;
    
    private static function modelSelect($whichSelectStatement) {
        switch($whichSelectStatement) {
            case self::GET_PICTURES_BLOB_BY_ID_STATEMENT:
                $result = self::$database->performQuery('Picture', self::GET_PICTURE_BLOB_BY_ID);
                if (count($result) == 0)
                    return NULL;
                return self::resultToPicturesArray($result)[0];
            case self::GET_PICTURES_BLOB_BY_GALLERY_ID_STATEMENT:
                $result = self::$database->performQuery('Picture', self::GET_PICTURES_BLOB_BY_GALLERY_ID);
                
                return self::resultToPicturesArray($result);
            case self::GET_X_PICTURES_BLOB_STATEMENT:
                $result = self::$database->performQuery('Picture', self::GET_X_PICTURES_BLOB);
                
                 return self::resultToPicturesArray($result);
            case self::GET_LAST_CREATED_PICTURE_ID_FOR_GALLERY_CONSTRAINT_STATEMENT:
                $result = self::$database->performQuery('Picture', self::GET_LAST_CREATED_PICTURE_ID_FOR_GALLERY_CONSTRAINT);
                $id = $result[0]['id'];
                return intval($id);
            case self::GET_TAGS_OF_PICTURE_STATEMENT:
                $result = self::$database->performQuery('Picture', self::GET_TAGS_OF_PICTURE);
                
                return Tag::tagsToArray($result);
            case self::HAS_USER_ACCESS_STATEMENT:
                $result = self::$database->performQuery('Picture', 'SELECT email FROM user AS u JOIN user_gallery AS ug ON u.id = ug.user_id JOIN gallery_pic AS gp ON ug.gallery_id = gp.gallery_id WHERE gp.pic_id = :pid');
                if (!$result)
                    return FALSE;
                return $result[0]['email'];
            case self::GET_GALLERY_STATEMENT:
                $result = self::$database->performQuery('Picture', 'SELECT  G.name, G.description, G.id FROM gallery AS g JOIN gallery_pic ON g.id = gallery_pic.gallery_id WHERE gallery_pic.pic_id = :pid');
                if (count($result) == 0)
                    return NULL;
                return Gallery::resultGalleryArray($result)[0];
            default:
                return NULL;
        }
    }
    
    public static function resultToPicturesArray($result) {
        $pics = array();
        foreach ($result as $pic) {
            $p = new Picture();
            $p->picture_blob = $pic['picture_blob'];
            $p->thumbnail_blob = $pic['thumbnail_blob'];
            $p->setPicture(self::blobToPic($pic['picture_blob']));
            $p->setThumbnail(self::blobToPic($pic['thumbnail_blob']));
            $p->setTag($pic['tag']);
            $p->setTitle($pic['title']);
            $p->setId($pic['id']);
            self::setQueryParameter(['pid' => $pic['id']]);
            $p->setTags(self::modelSelect(self::GET_TAGS_OF_PICTURE_STATEMENT));
            $pics[] = $p;
        }
        
        return $pics;
    }
    
    const ADD_PICTURE_STATEMENT = 1;
    const ADD_GALLERY_CONSTRAINT_STATEMENT = 2;
    
    private static function modelInsert($whichInsertStatement) {
        switch ($whichInsertStatement) {
            case self::ADD_PICTURE_STATEMENT:
                self::$database->performQuery('Picture', self::ADD_PICTURE);
                
                return true;
            case self::ADD_GALLERY_CONSTRAINT_STATEMENT:
                self::$database->performQuery('Picture', self::ADD_GALLERY_CONSTRAINT);
    
                return true;
            default:
                return false;
        }
    }
    
    const UPDATE_TAG_STATEMENT = 1;
    const UPDATE_TITLE_STATEMENT = 2;
    const UPDATE_THUMB_STATEMENT = 3;
    
    private static function modelUpdate($whichUpdateStatement) {
        switch($whichUpdateStatement) {
            case self::UPDATE_TAG_STATEMENT:
                self::$database->performQuery('Picture', self::UPDATE_TAG);
                return true;
            case self::UPDATE_TITLE_STATEMENT:
                self::$database->performQuery('Picture', self::UPDATE_TITLE);
                return true;
            case self::UPDATE_THUMB_STATEMENT:
                self::$database->performQuery('Picture', 'UPDATE pic SET thumbnail_blob = :thumb WHERE id = :id');
            default:
                return false;
        }
    }
    
    const DELETE_GALLERY_BY_ID_STATEMENT = 1;
    
    const DELETE_PICTURE_FROM_GALLERY_STATEMENT = 41;
    const DELETE_PICTURE_STATEMENT = 42;
    
    private static function modelDelete($whichDeleteStatement) {
        switch ($whichDeleteStatement) {
            case self::DELETE_GALLERY_BY_ID_STATEMENT:
                self::$database->performQuery('Picture', self::DELETE_GALLERY_BY_ID);
                return true;
            case self::DELETE_PICTURE_STATEMENT:
                self::$database->performQuery('Picture', self::DELETE_PICTURE);
                return true;
            case self::DELETE_PICTURE_FROM_GALLERY_STATEMENT:
                return self::$database->performQuery('Picture', self::DELETE_PICTURE_FROM_GALLERY);
            default:
                return false;
        }
    }
    
    public function getId() {
        
        return htmlentities($this->id);
    }
    
    public function setId($id) {
        
        $this->id = $id;
    }
    
    public function getTag() {
        
        return htmlentities($this->tag);
    }
    
    public function setTag($tag) {
        
        $this->tag = $tag;
    }
    
    public function getTitle() {
        
        return htmlentities($this->title);
    }
    
    public function setTitle($title) {
        
        $this->title = $title;
    }
    
    public function getPictureBlob() {
        
        return $this->picture_blob;
    }
    
    public function setPictureBlob($picture_blob) {
        
        $this->picture_blob = $picture_blob;
    }
    
    public function getNewThumb()
    {
        if (is_null($this->picture_blob))
            return FALSE;
        
        $image = imagecreatefromstring($this->picture_blob);
    
        $tempName = bin2hex(random_bytes(8)) . '.pic';
        
        imagepng($image, $tempName);
        
        list($width, $height) = getimagesize($tempName);
    
        $imgratio=$width/$height;
    
        //Ist das Bild höher als breit?
        if($imgratio>1)
        {
            $newwidth = THUMBNAIL_SIZE;
            $newheight = THUMBNAIL_SIZE/$imgratio;
        }
        else
        {
            $newheight = THUMBNAIL_SIZE;
            $newwidth = THUMBNAIL_SIZE*$imgratio;
        }
    
        $thumb = imagecreatetruecolor ($newwidth,$newheight);
        
        imagecopyresized($thumb, $image, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
    
        unlink($tempName);
        
        ob_end_clean();
        ob_start();
        imagepng($thumb);
        $contents = ob_get_contents();
        ob_end_clean();
        ob_start();
        imagedestroy($image);
        
        $this->thumbnail_blob = $contents;
        self::setQueryParameter(['id' => $this->getId(), 'thumb' => $contents]);
        self::modelUpdate(self::UPDATE_THUMB_STATEMENT);
        
        return $contents;
        
        //return "<img src='data:image/png;base64,".base64_encode($contents)."''/>";
    }
    
    public function getThumbnailBlob() {
        
        if (empty($this->thumbnail_blob))
            $this->getNewThumb();
        return $this->thumbnail_blob;
    }
    
    public function setThumbnailBlob($thumbnail_blob) {
        
        $this->thumbnail_blob = $thumbnail_blob;
    }
    
    public function getPicture() {
        
        return $this->picture;
    }
    
    public function setPicture($picture) {
        
        $this->picture = $picture;
    }
    
    public function getThumbnail() {
        
        return $this->thumbnail;
    }
    
    public function setThumbnail($thumbnail) {
        
        $this->thumbnail = $thumbnail;
    }
    
    public function getTags() {
        return $this->tags;
    }
    
    public function setTags($tagsArray) {
        $this->tags = $tagsArray;
    }
}