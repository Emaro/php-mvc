<?php
/**
 * User: Emaro
 * Date: 2017-03-31
 * Time: 10:23
 *
 * Each method need the prefix X (for Xecurity). Without it can't be called from a form
 */

include 'mvc/Controller/orders.php';


function Xlogin() {
    
    redirectUser();
    
    if (isset($_POST['username'], $_POST['password']) && !empty($_POST['username']) && !empty($_POST['password'])) {
        if (User::verifyUser($_POST['username'], $_POST['password'])) {
            $_SESSION[USER] = $_POST['username'];
            $_SESSION[TOKEN] = bin2hex(random_bytes(TOKEN_LEN));
            headerLocationView('home');
            exit;
        }
        return 'Falsche Anmeldedaten';
    }
    else {
        return 'Gibb überall etwas ein';
    }
}

function XuploadImage(){
    
    redirectGuest();
    
    if (isset($_POST['title'], $_POST['tags'], $_POST['galleryid']) && !empty($_POST['title']) && $_FILES['picture']['name'] != "") {
        
        if (!in_array(Gallery::getGalleryById($_POST['galleryid']), Gallery::getGalleriesByUserEmail($_SESSION[USER])))
            return 'Diese Galerie gehört nicht dir';
        
        $file_size = $_FILES['picture']['size'];
        
        $isImage = getimagesize($_FILES["picture"]['tmp_name']);
    
        if ($isImage !== FALSE) {
            if ($file_size < 4000000) {
                Picture::addPicture($_POST['galleryid'], $_POST['tags'], $_POST['title']);
                //header("Location: ./?view=overview");
                headerLocationView('gallery&id='. htmlentities($_POST['galleryid']));
            }
            return 'Das Bild ist zu gross';
        
        } else {
            return 'Der Upload ist kein Bild';
        }
    
    }
    return 'Angaben fehlen';
}

function XdeleteGallery()
{
    redirectGuest();
    
    if (!isset($_POST['pw'], $_POST['gid']))
        return "Bitte gib dein Passwort ein";
    
    if (!User::verifyUser($_SESSION[USER], $_POST['pw']))
        return "Passwort ungültig";
    
    if (!in_array(Gallery::getGalleryById($_POST['gid']), Gallery::getGalleriesByUserEmail($_SESSION[USER])))
        return 'Diese Galerie gehört nicht dir';
    
    $gallery = Gallery::getGalleryById($_POST['gid']);
    
    if (is_null($gallery))
        return "Ein Fehler ist aufgetreten";
    
    Gallery::deleteGalleryById($gallery->getId());
    
    header("Location: ");
}

function XremoveTagFromPic() {
    
    redirectGuest();
    
    if (!isset($_POST['pid'], $_POST['tagName']))
        return "Bitte wähle einen Tag zum entfernen aus";
    
    $userIsOwner = FALSE;
    
    foreach (Gallery::getGalleriesByUserEmail($_SESSION[USER]) as $gallery)
    {
        if (in_array(Picture::getPictureById($_POST['pid']), Picture::getPicturesFromGallery($gallery->getId())))
            $userIsOwner = TRUE;
    }
    
    if (!$userIsOwner)
        return 'Das Bild gehört nicht dir';
    
    $picture = Picture::getPictureById($_POST['pid']);
    $tag = Tag::getTagByName($_POST['tagName']);
    
    Tag::removePictureTagConstraint($tag->getId(), $picture->getId());
    header("Location: ");
}

function XaddTagToPic() {
    redirectGuest();
    
    if (!isset($_POST['pid'], $_POST['tagName']))
        return "Bitte wähle einen Tag zum entfernen aus";
    
    $userIsOwner = FALSE;
    
    foreach (Gallery::getGalleriesByUserEmail($_SESSION[USER]) as $gallery)
    {
        if (in_array(Picture::getPictureById($_POST['pid']), Picture::getPicturesFromGallery($gallery->getId())))
            $userIsOwner = TRUE;
    }
    
    if (!$userIsOwner)
        return 'Das Bild gehört nicht dir';
    
    
    $picture = Picture::getPictureById($_POST['pid']);
    $picture->addTag($_POST['tagName']);
    header("Location: ");
}

function Xsearch() {
    
    redirectGuest();
    if (!isset($_REQUEST['search']))
        return "Bitte gebe einen Suchbegriff ein";
    
    return Tag::searchPicturesByUser($_REQUEST['search'], $_SESSION[USER]) ?: 'Es wurden keine Bilder mit diesem Tag gefunden';
}

function XdeletePicture()
{
    
    redirectGuest();
    
    if (!isset($_POST['pictureId']))
        return 'Ungültige ID';
    
    if (is_nan($_POST['pictureId']))
        return 'Ungültige ID';
    
    $userIsOwner = FALSE;
    
    foreach (Gallery::getGalleriesByUserEmail($_SESSION[USER]) as $gallery)
    {
        if (in_array(Picture::getPictureById($_POST['pictureIdw']), Picture::getPicturesFromGallery($gallery->getId())))
            $userIsOwner = TRUE;
    }
    
    if (!$userIsOwner)
        return 'Das Bild gehört nicht dir';
    
    
    Picture::deletePictureById($_POST['pictureId']);
    
    $_GET['picture'] = '';
    return 'Bild erfolgreich gelöscht';
}