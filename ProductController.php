<?php

namespace App\Controller;

use Pimcore\Model;
use Carbon\Carbon;
use Pimcore\Model\DataObject;
//use Pimcore\\Model\\DataObject\\Products\\Listing;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyObjectRelation;
use Pimcore\Model\DataObject\ClassDefinition\Data\Relations\AbstractRelations;
use Pimcore\Model\DataObject\ClassDefinition\Data\Date;
use Pimcore\Model\DataObject\ClassDefinition\Data\ReverseObjectRelation;
use Pimcore\Model\DataObject\PortalUser;
use Pimcore\Model\DataObject\PortalUserGroup;
use Pimcore\Model\DataObject\Data\QuantityValue;
use Pimcore\Model\DataObject\QuantityValue\Unit;
use Pimcore\Model\DataObject\QuantityValue\UnitConversionService;
use Pimcore\Model\Translation;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\GSMBrands;
use Pimcore\Model\DataObject\Productcategory;
use Pimcore\Model\DataObject\SubCategories;
use Symfony\Component\EventDispatcher\GenericEvent;
use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\DataObject\AllProducts;
use Pimcore\Model\Element;
use Pimcore\Model\Schedule\Task;
use Pimcore\Model\Version;
use Pimcore\Tool;
use Symfony\Component\Mime\MimeTypes;
use Pimcore\File;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Registry;
use Pimcore\Workflow\Service;
use Pimcore\Event\AdminEvents;
// use App\Controller\NotificationService;
use Pimcore\Model\Notification\Service\NotificationService;
use Pimcore\Db;
use Pimcore\Model\User;
use Symfony\Component\HttpFoundation\JsonResponse;
// use  Pimcore\Model\Element\Service;

/**
 * @Route("/product")
 */
class ProductController extends FrontendController
{

    /**
     * @var DataObject\Service
     */
    protected DataObject\Service $_objectService;

    /**
     * @var array
     */
    private array $objectData = [];

    /**
     * @var array
     */
    private array $metaData = [];

    private array $classFieldDefinitions = [];

    /**
     * @Route("/edit_product", name="edit_product", methods={"GET"})
     */
    public function edit_product(Request $request)
    {
        $id = $request->get('poolId');
        $idarray = explode('~', $id);

        //Kasthuri
        $idValue = isset($idarray[1]) ? $idarray[1] : 'default_value'; // Replace 'default_value' with an appropriate default
        //Kasthuri
        return $this->getAction($idValue, false, '');
    }
    /**
     * @Route("/create-product", name="create_product", methods={"POST"})
     */
    public function create_productAction(Request $request, NotificationService $notificationService)
    {

        $content = $request->request->all();
        // var_dump($content); 
        // print_r('create_productAction');
        // exit;
        $objectFromDatabase = DataObject\Concrete::getById((int) $request->get('id'));
        $versionIds = [];
        $oldVersionNo = $objectFromDatabase->getVersionCount();  //
        $versionIds[] = $oldVersionNo;
        // print_r($versionIds);
        // exit;

        if (!$objectFromDatabase instanceof DataObject\Concrete) {
            echo json_encode(array('success' => false, 'message' => 'Could not find object'));
        }
        $object = $this->getLatestVersion($objectFromDatabase);
        $portalUser = $this->getUser();

        $object->setUserModification($portalUser->getpimcoreUser());
        $objectFromVersion = $object !== $objectFromDatabase;
        // $newVersionNo = $objectFromVersion->getVersionCount();
        // print_r("new version: ".$objectFromVersion);
        // exit;


        $originalModificationDate = $objectFromVersion ? $object->getModificationDate() : $objectFromDatabase->getModificationDate();
        if ($objectFromVersion) {
            if (method_exists($object, 'getLocalizedFields')) {
                /** @var DataObject\Localizedfield $localizedFields */
                $localizedFields = $object->getLocalizedFields();
                $localizedFields->setLoadedAllLazyData();
            }
        }
        // print_r($objectFromDatabase->getParentId());
        // exit;
        $updated = array();
        $missing = array();
        $editedFields = array();

        if ($request->get('ProductName') != "") {
            $ObjectParentId = '';
            $parentID = '';
            if ($object->getParent()->getType() != 'object') {
                if ($request->get('Brand') != "") {
                    //  $object->setParentId(3020);
                    $reqParentId = 576;
                    $parent = DataObject::getById($reqParentId);
                    if (!DataObject\Service::pathExists($parent->getRealFullPath() . '/' . $request->get('Brand'))) {

                        $folder = DataObject\Folder::create([
                            'o_parentId' => $reqParentId,
                            'o_creationDate' => time(),
                            'o_userOwner' => $portalUser->getpimcoreUser(),
                            'o_userModification' => $portalUser->getpimcoreUser(),
                            'o_key' => $request->get('Brand'),
                            'o_published' => true,
                        ]);
                        $folder->save();
                        $object->setParentId($folder->getId());
                        $ObjectParentId = $folder->getId();
                        // exit;
                    } else {
                        $folder = DataObject\Folder::getByPath($parent->getRealFullPath() . '/' . $request->get('Brand'));
                        $object->setParentId($folder->getId());
                        $ObjectParentId = $folder->getId();
                        // exit;
                    }


                    // echo $request->getParent();
                    // exit;

                    // exit;
                }

                if ($ObjectParentId !== "" && $request->get('Category') != "") {
                    $reqParentId = $ObjectParentId;
                    $parent = DataObject::getById($reqParentId);
                    if (!DataObject\Service::pathExists($parent->getRealFullPath() . '/' . $request->get('Category'))) {

                        $folder = DataObject\Folder::create([
                            'o_parentId' => $reqParentId,
                            'o_creationDate' => time(),
                            'o_userOwner' => $portalUser->getpimcoreUser(),
                            'o_userModification' => $portalUser->getpimcoreUser(),
                            'o_key' => $request->get('Category'),
                            'o_published' => true,
                        ]);
                        $folder->save();
                        $object->setParentId($folder->getId());

                        $ObjectParentId = $folder->getId();
                        // exit;
                    } else {
                        $folder = DataObject\Folder::getByPath($parent->getRealFullPath() . '/' . $request->get('Category'));
                        $object->setParentId($folder->getId());
                        $ObjectParentId = $folder->getId();
                        // exit;
                    }
                }

                if ($ObjectParentId !== "" && $request->get('Category') != "" && $request->get('SubCategory') != "") {
                    $reqParentId = $ObjectParentId;
                    $parent = DataObject::getById($reqParentId);
                    if (!DataObject\Service::pathExists($parent->getRealFullPath() . '/' . $request->get('SubCategory'))) {

                        $folder = DataObject\Folder::create([
                            'o_parentId' => $reqParentId,
                            'o_creationDate' => time(),
                            'o_userOwner' => $portalUser->getpimcoreUser(),
                            'o_userModification' => $portalUser->getpimcoreUser(),
                            'o_key' => $request->get('SubCategory'),
                            'o_published' => true,
                        ]);
                        $folder->save();
                        $object->setParentId($folder->getId());
                        $ObjectParentId = $folder->getId();
                        // exit;
                    } else {
                        $folder = DataObject\Folder::getByPath($parent->getRealFullPath() . '/' . $request->get('SubCategory'));
                        $object->setParentId($folder->getId());
                        $ObjectParentId = $folder->getId();
                        // exit;
                    }
                }

            } else {

                // print_r($object->getParentId());
                // exit;
                /* $parent = $object->getParent();
                
                $folder =  DataObject\Folder::getByPath($parent->getRealFullPath());
                print_r($folder);
                exit;
                $object->setParentId($folder->getId());
                $ObjectParentId = $folder->getId(); */

            }
            if ($request->get('isAdd') == true) {
                $object->setKey(trim($request->get('ProductName')));

                $object->save();
            } else if ($ObjectParentId != '' && $objectFromDatabase->getParentId() != $ObjectParentId) {

                $object->save();
            }
        }


        foreach ($request->request->all() as $key => $value) {
            if ($value == "" || $key == 'Submit' || $key == 'isAdd') {
                array_push($missing, $key);
                // $object->setValue('ProductName', $request->get('ProductName'));
            } else {
                // print_r($key);
                $dateArray = array('SalePriceStart', 'SalePriceEnd');
                $unitArray = array('Weight', 'Weight__value', 'Length', 'Length__value', 'Width', 'Width__value', 'Height', 'Height__value', 'Depth', 'Depth', 'Depth__value', 'Cubage', 'Cubage__value');
                // echo $key;
                if (in_array($key, $dateArray)) {
                    $editedFields[] = $key;
                    $converted = strtotime($request->get($key));
                    $date = new Date();
                    $object->setValue($key, $date->getDataFromGridEditor($converted));
                    // print_r($object);
                    // exit;

                } else if (in_array($key, $unitArray)) {
                    if (!str_contains($key, '__value')) {
                        $object->setValue($key, new DataObject\Data\QuantityValue($request->get($key), $request->get($key . '__value')));
                        $editedFields[] = $key;
                    }
                } else if ($key == 'Specifications') {

                    $fd = $object->getClass()->getFieldDefinition($key);
                    // print_r($fd);exit;
                    if ($fd) {
                        $func = 'get' . $key;

                        if ($object->{$func}()->getItems()) {
                            $deleted = array('type' => $object->{$func}()->getItems()[0]->getType(), 'data' => 'deleted');
                            $datasDeleted = array('data' => $deleted);

                            $object->setValue($key, $fd->getDataFromEditmode($datasDeleted, $object, ['objectFromVersion' => $objectFromVersion]));
                        }
                        if ($request->request->get($key)['type'] != "") {
                            $editedFields[] = $key;
                            $datas = array('data' => $value);
                            // print_r($datas);
                            $object->setValue($key, $fd->getDataFromEditmode($datas, $object, ['objectFromVersion' => $objectFromVersion]));
                        }

                    }


                } else if ($key == 'VariantAttributes') {


                    $fd = $object->getClass()->getFieldDefinition($key);
                    // print_r($fd);exit;
                    if ($fd) {
                        $func = 'get' . $key;

                        if ($object->{$func}()->getItems()) {
                            $deleted = array('type' => $object->{$func}()->getItems()[0]->getType(), 'data' => 'deleted');
                            $datasDeleted = array('data' => $deleted);

                            $object->setValue($key, $fd->getDataFromEditmode($datasDeleted, $object, ['objectFromVersion' => $objectFromVersion]));
                        }
                        if ($request->request->get($key)['type'] != "") {
                            $editedFields[] = $key;
                            $datas = array('data' => $value);
                            // print_r($datas);
                            $object->setValue($key, $fd->getDataFromEditmode($datas, $object, ['objectFromVersion' => $objectFromVersion]));
                        }

                    }


                } else {


                    if ($key == 'Brand') {
                        //  echo ucwords(strtolower($value)).'<br>'; exit;
                        $brandDetail = GSMBrands::getByBrandName(ucwords(strtolower($value)))->getObjects()[0];
                        $bandDatavaule = array("id" => $brandDetail->getId(), "type" => "object", "subtype" => "object", "path" => $brandDetail->getFullPath());
                        $fd = $object->getClass()->getFieldDefinition('Brands');
                        $abcd = $fd->getDataFromEditmode($bandDatavaule, $object, ['objectFromVersion' => $objectFromVersion]);
                        //print_r($abcd);
                        // exit;
                        $object->setValue('Brands', $abcd);
                        $object->setValue($key, $request->get($key));
                    } else if ($key == 'Category') {
                        // echo "Category";
                        //  echo ucwords(strtolower($value)).'<br>'; exit;
                        $categoryDetail = Productcategory::getByCategory(ucwords(strtolower($value)))->getObjects()[0];
                        $categoryDatavaule = array("id" => $categoryDetail->getId(), "type" => "object", "subtype" => "object", "path" => $categoryDetail->getFullPath());
                        $fd = $object->getClass()->getFieldDefinition('Categories');
                        $abcd = $fd->getDataFromEditmode($categoryDatavaule, $object, ['objectFromVersion' => $objectFromVersion]);
                        //print_r($abcd);
                        // exit;
                        $object->setValue('Categories', $abcd);
                        $object->setValue($key, $request->get($key));
                    } else if ($key == 'SubCategory') {
                        // echo "Category";
                        //  echo ucwords(strtolower($value)).'<br>'; exit;
                        $categoryDetail = SubCategories::getByName(ucwords(strtolower($value)))->getObjects()[0];
                        $categoryDatavaule = array("id" => $categoryDetail->getId(), "type" => "object", "subtype" => "object", "path" => $categoryDetail->getFullPath());
                        $fd = $object->getClass()->getFieldDefinition('SubCategories');
                        $abcd = $fd->getDataFromEditmode($categoryDatavaule, $object, ['objectFromVersion' => $objectFromVersion]);
                        //print_r($abcd);
                        // exit;
                        $object->setValue('SubCategories', $abcd);
                        $object->setValue($key, $request->get($key));
                    } else {
                        $fd = $object->getClass()->getFieldDefinition($key);
                        // print_r($fd);exit;
                        if ($fd instanceof \Pimcore\Model\DataObject\ClassDefinition\Data\BooleanSelect) {
                            $object->setValue($key, $fd->getDataFromEditmode($request->get($key), $object, ['objectFromVersion' => $objectFromVersion]));
                        } else {

                            $object->setValue($key, $request->get($key));
                        }
                    }
                    $editedFields[] = $key;

                }
                // array_push($updated, $key);
                // print_r($object);
            }

        }
        foreach ($missing as $mkey => $mvalue) {
            if ($request->get('isAdd') == false) {
                // 
                if ($mvalue != "isAdd" && !str_contains($mvalue, '__value')) {

                    $oldValue = $objectFromDatabase->get($mvalue);

                    $newValue = $request->get($mvalue);

                    if ($oldValue != "" & $oldValue != $newValue) {


                        $object->setValue($mvalue, null);
                    }
                }
                /* if($mvalue !="isAdd" && $mvalue!="Submit"){
                  
                   echo  $oldValue = $objectFromDatabase->get($mvalue);
                   echo    $newValue = $request->get($mvalue);
                } */
            }

        }
        if ($_FILES['ThumbnailImage'] && $_FILES['ThumbnailImage']['name'] != "") {
            // $editedFields[] = 'ThumbnailImage';
            $asset = $this->addAssets($_FILES['ThumbnailImage'], 'ThumbnailImage', '', $request->get('Brand'), $request->get('Category'), $request->get('SubCategory'), $request->get('SKU'));
            $advancedImage = new DataObject\Data\Hotspotimage();
            $advancedImage->setImage($asset1 = \Pimcore\Model\Asset::getById($asset->getId()));
            $object->setThumbnailImage($advancedImage);
        }
        if ($_FILES['ImageGallery']) {

            $noFile = $_FILES['ImageGallery']['size'][0] === 0
                && $_FILES['ImageGallery']['tmp_name'][0] === '';
            if (!$noFile) {
                // echo "exit";
                // $editedFields[] = 'ImageGallery';
                $items = [];
                $gallery = $object->getImageGallery();
                for ($i = 0; $i < count($_FILES['ImageGallery']['name']); $i++) {
                    $asset = $this->addAssets($_FILES['ImageGallery'], 'ImageGallery', $i, $request->get('Brand'), $request->get('Category'), $request->get('SubCategory'), $request->get('SKU'));
                    // $image = Asset::getById($asset->getId());
                    $advancedImage1 = new DataObject\Data\Hotspotimage();
                    $advancedImage1->setImage(\Pimcore\Model\Asset::getById($asset->getId()));
                    // $gallery->setItems($advancedImage1);
                    // $object->setImageGallery($advancedImage1);
                    $items[] = $advancedImage1;

                }
                $imageGallery = new \Pimcore\Model\DataObject\Data\ImageGallery($items);
                $object->setImageGallery($imageGallery);
                // $object->setImageGallery(new \Pimcore\Model\DataObject\Data\ImageGallery($items));


            }

        }

        // exit;
        // $object->setPublished(true);
        // $object->save();
        // if($object->save()){

        $userGroups = $portalUser->getGroups();
        $currentUserRole = [];

        foreach ($userGroups as $userGroup) {
            $currentUserRole[] = strtolower($userGroup->getKey());
        }

        // $changes = [];
        // // foreach ($request->request->all() as $key => $value) {
        //     $oldValue = $objectFromDatabase->getPageTitle($key);

        //     if ($value != $oldValue) {
        //         $changes[$key] = [
        //             'old' => $oldValue,
        //             'new' => $value,
        //         ];
        //     }
        // // }



        // Get the edited fields dynamically

        // print_r($editedFields);
        // exit; 
        // Check user role and send notification
        if (in_array('admin', $currentUserRole)) {
            //     print_r($object);
            //   exit;
            $object->setPublished(true);

            if ($object->save()) {
                //          print_r($object);
                //   exit;
                $cid = $object->getId();
                // print_r($cid);
                // exit;
                $newVersionNo = $object->getVersionCount();
                $versionIds[] = $newVersionNo;
                // print_r($versionIds);
                // exit;
                // print_r($newVersionNo);
                // print_r($oldVersionNo); exit;
                if ($newVersionNo && $oldVersionNo) {
                    $objectId = '';
                    foreach ($versionIds as $versionCount) {
                        $list = new Version\Listing();
                        $list->setLoadAutoSave(true);
                        $list->setCondition('cid = ? AND ctype = ? AND versionCount = ? AND (autoSave=0 OR (autoSave=1)) ', [
                            $cid, "object", $versionCount,
                        ])
                            ->setOrderKey('date')
                            ->setOrder('ASC');

                        $versions = $list->load();
                        $versions = Element\Service::getSafeVersionInfo($versions);
                        $versions = array_reverse($versions);
                        $publishedVersionId = $versions[0]['id'];
                        $version = \Pimcore\Model\Version::getById($publishedVersionId);
                        $versionId[] = $version->getId();
                        // $objectId =$version->getCid();
                        // print_r($version ->getId());
                    }
                    // print_r($cid);
                    // print_r($versionId[0]);
                    // print_r($versionId[1]);
                    // exit;

                    $vr = $this->forward('App\Controller\WebsiteIntegrationController::getProductDetails', [
                        'objectId' => $cid,
                        'from' => $versionId[0],
                        'to' => $versionId[1],

                    ]);
                    // print_r("Version status: ".$vr);
                    // exit;
                }

                //  return $this->getAction($request->get('id'), 'Product edited and published by an admin.');
                // header('location:edit_product?poolId=o~'.$request->get('id'));
                if ($request->get('isAdd') == true) {
                    echo "<script>alert('Product has been created Successfully'); window.location.href='edit_product?poolId=o~" . $request->get('id') . "';</script>";
                } else {
                    echo "<script>alert('Product has been updated Successfully'); window.location.href='edit_product?poolId=o~" . $request->get('id') . "';</script>";
                }
                exit;
            } else {
                // return $this->getAction($request->get('id'), 'Unable to edit and publish product. Please try again.');
                // header('location:edit_product?poolId=o~'.$request->get('id'));
                if ($request->get('isAdd') == true) {
                    echo "<script>alert('Unable to Add Product at this moment. Please try again'); window.location.href='/';</script>";
                } else {
                    echo "<script>alert('Unable to Update Product at this moment. Please try again'); window.location.href='edit_product?poolId=o~" . $request->get('id') . "';</script>";
                }
                exit;
            }
        } elseif (in_array('editor', $currentUserRole)) {
            if ($object->saveVersion(true, true, null, false)) {

                // echo 'editor';
                $changes = [];
                // $editedFields = ['PageTitle', 'MetaDescription']; // Add the fields you want to track
                /*
                foreach ($editedFields as $field) {
                    // Skip fields that don't need tracking
                    // if ($field === 'Submit') {
                        // continue;
                    // }
                    
                   
                    if(in_array($field, $unitArray)){
                        $oldValue = $objectFromDatabase->get($field);
                       $newValue = new DataObject\Data\QuantityValue($request->get($field), $request->get($field . '__value'));
                       
                    }else if(in_array($field, $dateArray)){
                         $oldValue = $objectFromDatabase->get($field);
                          $newValue = date('Y-m-d H:i:s', strtotime($request->get($field)));
                    }else if($field =='Specifications'){
                        $func = 'get' . $field;
                        $oldValue = $objectFromDatabase->{$func}()->getItems()[0]->getType();
                        //print_r($oldValue);
                        
                         $newValue = $request->get($field)['type'];
                        
                        
                    }else{
                        $oldValue = $objectFromDatabase->get($field);
                        $newValue = $request->get($field);
                         
                    }
                    
                    if ($newValue != $oldValue) {
                        $changes[$field] = [
                            'old' => $oldValue,
                            'new' => $newValue,
                        ];
                    }
                
                   
                } */



                $notificationSent = $this->sendNotificationToAdmin(
                    $notificationService,
                    $portalUser,
                    $object->getFullPath(),
                    $object->getId(),
                    $changes
                );
                // print_r($notificationSent);
                if ($notificationSent) {
                    // $this->edit_product($request); // Call the edit_product method
                    // return $this->getAction($request->get('id'), 'Product edited, version saved, and notification sent.');
                    if ($request->get('isAdd') == true) {
                        echo "<script>alert('Product has been created successfully and sent for Approval.'); window.location.href='edit_product?poolId=o~" . $request->get('id') . "';</script>";
                    } else {
                        echo "<script>alert('Product has been updated successfully and sent for Approval.'); window.location.href='edit_product?poolId=o~" . $request->get('id') . "';</script>";
                    }
                    exit;
                } else {
                    // return $this->getAction($request->get('id'), 'Product edited and version saved, but notification sending failed.');
                    // header('location:edit_product?poolId=o~'.$request->get('id'));
                    echo "<script>alert('Product edited and version saved, but notification sending failed'); window.location.href='edit_product?poolId=o~" . $request->get('id') . "';</script>";
                    exit;
                }
            } else {
                // return $this->getAction($request->get('id'), 'Unable to edit product. Please try again.');
                // header('location:edit_product?poolId=o~'.$request->get('id'));
                echo "<script>alert('Unable to edit product. Please try again.'); window.location.href='edit_product?poolId=o~" . $request->get('id') . "';</script>";
                exit;
            }
        } else {
            //  return $this->getAction($request->get('id'), 'Unauthorized role to edit product.');
            //   header('location:edit_product?poolId=o~'.$request->get('id'));
            echo "<script>alert('Unauthorized role to edit product.'); window.location.href='edit_product?poolId=o~" . $request->get('id') . "';</script>";
            exit;
        }
    }


    /**
     * Send a notification to the admin if the user is an editor.
     *
     * @param NotificationService $notificationService
     * @param int $portalUserId
     * @param string $productPath
     * @param int $productId
     * @param array $changes Array of edited and existing values
     * @return void
     */
    private function sendNotificationToAdmin(NotificationService $notificationService, $portalUser, $productPath, $productId, $changes)
    {
        $element = DataObject::getById($productId);
        //   print_r($element);
//   exit;
        $editorName = $this->getEditorName($portalUser->getId()); // Get the editor's name
        $message = 'The product ' . $element->getProductName() . ' ( ' . $element->getSKU() . ' )  has been modified recently and it\'s waiting for approval.';
        // Inside the sendNotificationToAdmin method
        // $message = sprintf('Product edited by editor %s. Product Path: %s, Product ID: %d', $editorName, $productPath, $productId);
        $editedFields = [];
        /*
        if (!empty($changes)) {
            $message .= "\nChanges:";
        
            foreach ($changes as $field => $change) {
              
                    $oldValue = is_array($change['old']) ? implode(', ', $change['old']) : $change['old'];
                    $newValue = is_array($change['new']) ? implode(', ', $change['new']) : $change['new'];
               
        
                $editedFields[] = "$field: changed from '{$oldValue}' to '{$newValue}'";
            } 
        
            // $message .= "\n- " . implode("\n- ", $editedFields);

       
        
        // // Send the notification to the admin user from the system user.
        // $notificationService->sendToUser(
         //   2, // Admin user ID
        //    1, // System user ID (replace with the actual system user ID)
        //    'Product Edit Notification', // Notification title
        //    $message
       // );
        
       
        
        // $notificationService->sendToGroup(
        //     889, // Admin user ID
        //     1, // System user ID (replace with the actual system user ID)
        //     'Product Edit Notification', // Notification title
        //     $message, // Notification message
        //     $element
        // );
        
        } */
        $this->sendToGroup(
            $notificationService,
            889,
            $portalUser->getpimcoreUser(),
            'Product Edit Notification',
            $message,
            $element
        );
        // print_r($portalUser->getpimcoreUser());
        // exit;
        $notificationService->sendToUser(
            $portalUser->getpimcoreUser(), // System user ID (replace with the actual system user ID) 
            2, // Admin user ID
            'Product Edit Notification', // Notification title
            "Product " . $element->getProductName() . " ( " . $element->getSKU() . " ) has been sent for Approval",
            $element
        );

        return true;
    }


    /**
     * Get the name of the editor based on their ID.
     *
     * @param int $editorId
     * @return string
     */
    private function getEditorName($editorId)
    {
        // Replace this with the actual logic to fetch the editor's name based on their ID from the database.
        $query = Db::get()->prepare("SELECT firstname, lastname FROM object_query_portaluser WHERE oo_id = ?");
        $query->execute([$editorId]);

        $editorData = $query->fetch();

        if ($editorData) {
            return $editorData['firstname'] . ' ' . $editorData['lastname'];
        }

        return 'Unknown Editor';
    }


    public function addAssets($file, $name, $key, $brand = '', $category = '', $subcategory = '', $sku = '')
    {
        $defaultUploadPath = $config['assets']['default_upload_path'] ?? '/';
        
        if ($defaultUploadPath == '/') {
            $defaultUploadPath .= "Product Images/";
        }
    
        if ($brand != "") {
            $defaultUploadPath .= $brand . "/";
        }
        if ($category != "") {
            $defaultUploadPath .= $category . "/";
        }
        if ($subcategory != '') {
            $defaultUploadPath .= $subcategory . "/";
        }
        if ($sku != '') {
            $defaultUploadPath .= $sku . "/";
        }

        $portalUser = $this->getUser();

        if ($key == '') {
            if (array_key_exists($name, $_FILES)) {

                $filename = $_FILES[$name]['name'];
                $sourcePath = $_FILES[$name]['tmp_name'];
            }
        } else {
            $filename = $_FILES[$name]['name'][$key];
            $sourcePath = $_FILES[$name]['tmp_name'][$key];

        }

        $filename = Element\Service::getValidKey($filename, 'asset');
        if (empty($filename)) {
            throw new \Exception('The filename of the asset is empty');
        }
        $uploadAssetType = 'image';
        if ($uploadAssetType) {
            $mimetype = MimeTypes::getDefault()->guessMimeType($sourcePath);
            $assetType = Asset::getTypeFromMimeMapping($mimetype, $filename);

            if ($uploadAssetType !== $assetType) {
                throw new \Exception("Mime type $mimetype does not match with asset type: $uploadAssetType");
            }
        }
        $parentId = Asset\Service::createFolderByPath($defaultUploadPath)->getId();
        $parentAsset = Asset::getById((int) $parentId);
        if (Asset\Service::pathExists($parentAsset->getRealFullPath() . '/' . $filename)) {
            $asset = Asset::getByPath($parentAsset->getRealFullPath() . '/' . $filename);
            $asset->setStream(fopen($sourcePath, 'rb', false, File::getContext()));
            $asset->save();
        } else {
            $asset = Asset::create($parentId, [
                'filename' => $filename,
                'sourcePath' => $sourcePath,
                'userOwner' => $portalUser->getpimcoreUser(),
                'userModification' => $portalUser->getpimcoreUser(),
            ]);
        }
        return $asset;
    }
    /**
     * @Route("/products/brand_list_api/", name="brand_list_api")
     * @param Request $request
     * @return Response
     * throws \Exception
     */
    public function brand_listAction()
    {
        //Getting the list of brand objects
        $items = GSMBrands::getList();

        $i = 0;
        $hostURL = \Pimcore\Tool::getHostUrl();

        foreach ($items as $myObject) {

            //Brand Details & Media
            $data[$i]['id'] = $myObject->getID();
            $data[$i]['BrandName'] = $myObject->getBrandName();
            $data[$i]['BrandCode'] = $myObject->getBrandCode();
            //Asset Details
            $asset = \Pimcore\Model\Asset::getByPath($myObject->getLogo());
            if ($asset != "") {
                $data[$i]['Logo'] = $hostURL . $asset->getFullPath();
            }
            $i++;
        }
        return $this->json(["success" => true, "data" => $data]);
    }

    /**
     * @Route("/products/category_list_api/", name="category_list_api")
     *
     * @param Request $request
     * @return Response
     * throws \Exception
     */
    public function category_listAction(Request $request)
    {
        $items = Productcategory::getList();
        $brandIdToCheck = 489; // Replace 501 with the actual brand ID you want to check
        $data = array();

        foreach ($items as $myObject) {
            $brand = $myObject->getBrand();
            $categoryNames = null;

            if (is_array($brand)) {
                foreach ($brand as $brandItem) {
                    if ($brandItem->getId() === $brandIdToCheck) {
                        $categoryNames = $myObject->getCategory();
                        $data[] = [
                            'categoryId' => $myObject->getID(),
                            'categoryName' => $categoryNames
                        ];
                    }
                }
            } elseif (!is_null($brand) && $brand->getId() === $brandIdToCheck) {
                $categoryNames = $myObject->getCategory();
                $data[] = [
                    'categoryId' => $myObject->getID(),
                    'categoryName' => $categoryNames
                ];
            }
        }

        return $this->json(["success" => true, "data" => $data]);
    }

    /**
     * @Route("/unique_filed_check/", name="unique_filed_check",  methods="GET")
     *
     * @param Request $request
     * @return Response
     * throws \Exception
     */
    public function unique_filed_check(Request $request)
    {
        // print_r($request->get('id'));
        $object_id = $request->get('id');
        $entries = new DataObject\AllProducts\Listing();
        $entries->setCondition("o_id != $object_id");
        // use prepared statements! Mysqli only supports ? placeholders // or
        $keyname = '';
        foreach ($request->query->all() as $key => $value) {
            if ($key != 'id') {
                $keyname = $key;
                $keyValue = $request->get($key);
                $entries->addConditionParam("$key ='$keyValue'");
            }

        }
        // echo count($entries);
        if (count($entries)) {
            $response = new Response(json_encode("$keyname has to be unique"));
        } else {
            $response = new Response(json_encode(true));
        }


        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    /**
     * @Route("/unique_upc_check/", name="unique_upc_check",  methods="GET")
     *
     * @param Request $request
     * @return Response
     * throws \Exception
     */
    public function unique_upc_check(Request $request)
    {
        // print_r($request->get('id'));
        $object_id = $request->get('id');
        $entries = new DataObject\AllProducts\Listing();
        $entries->setCondition("o_id != $object_id");
        // use prepared statements! Mysqli only supports ? placeholders // or
        $keyname = '';
        foreach ($request->query->all() as $key => $value) {
            if ($key != 'id') {
                $keyname = $key;
                $keyValue = $request->get($key);
                if ($keyValue != "") {
                    $entries->addConditionParam("$key ='$keyValue'");
                }
            }

        }
        // echo count($entries);
        if (count($entries)) {
            $response = new Response(json_encode("$keyname has to be unique"));
        } else {
            $response = new Response(json_encode(true));
        }


        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    /**
     * @Route("/products/subcategory_list_api/", name="category_list_api")
     * @param Request $request
     * @return Response
     * throws \Exception
     */
    public function subcategory_listAction(Request $request)
    {
        $items = SubCategories::getList();
        $categoryIdToCheck = 531; // Replace 155 with the actual category ID you want to check
        $data = array();

        foreach ($items as $myObject) {
            $category = $myObject->getParentCategory();
            $subCategoryNames = null;

            if (is_array($category)) {
                foreach ($category as $categoryItem) {
                    if ($categoryItem->getId() === $categoryIdToCheck) {
                        $subCategoryNames = $myObject->getName();
                        $data[] = [
                            'subCategoryId' => $myObject->getID(),
                            'subCategoryName' => $subCategoryNames
                        ];
                    }
                }
            } elseif (!is_null($category) && $category->getId() === $categoryIdToCheck) {
                $subCategoryNames = $myObject->getName();
                $data[] = [
                    'subCategoryId' => $myObject->getID(),
                    'subCategoryName' => $subCategoryNames
                ];
            }
        }
        return $this->json(["success" => true, "data" => $data]);
    }

    /**
     * @Route("/category-single", name="category_single")
     */
    public function category_singleAction(Request $request)
    {
        //Getting Objects
        $myObject = DataObject\Productcategory::getById(155);

        //Category Details
        $data['id'] = $myObject->getID();
        $data['Category'] = $myObject->getCategory();
        if ($myObject->getBrand() != "") {
            $data['BrandId'] = $myObject->getBrand()->getId();
            $data['Brand'] = $myObject->getBrand()->getBrandName();
        }
        return $this->json(["success" => true, "data" => $data]);
    }

    /**
     * @Route("/add_product", name="add_product")
     * @param Request $request
     */
    public function add_productAction(Model\FactoryInterface $modelFactory, Request $request)
    {

        $reqParentId = 576;

        $parent = DataObject::getById($reqParentId);
        $reqKey = 'TestProduct_' . rand(100000, 999999);
        $reqClassName = str_replace(' ', '', $parent->getKey());
        $reqClassId = str_replace(' ', '', $parent->getKey());
        // print_r($parent);
        /* if (!$parent->isAllowed('create')) {
            $message = 'prevented adding object because of missing permissions';
            echo $message;
            exit;
        } */
        $intendedPath = $parent->getRealFullPath() . '/' . $reqKey;
        if (DataObject\Service::pathExists($intendedPath)) {
            $message = 'prevented creating object because object with same path+key already exists';

        }
        $className = 'Pimcore\\Model\\DataObject\\' . ucfirst($reqClassName);
        /** @var DataObject\Concrete $object */
        $object = $modelFactory->build($className);
        $object->setOmitMandatoryCheck(true); // allow to save the object although there are mandatory fields
        $object->setClassId($reqClassId);

        $object->setClassName($reqClassName);
        if ($request->get("poolId") != "") {
            $parentReq = explode('~', $request->get("poolId"));
            $object->setParentId($parentReq[1]);
            // $object->setType(DataObject::OBJECT_TYPE_VARIANT);
            $object->setValue('ExternalProductID', " ");
            $object->setValue("ProductType", "Variant");

        } else {
            $object->setParentId($reqParentId);
        }
        $object->setKey($reqKey);
        $object->setCreationDate(time());
        /** @var PortalUser $portalUser */
        $portalUser = $this->getUser();
        $object->setUserOwner($portalUser->getpimcoreUser());
        $object->setUserModification($portalUser->getpimcoreUser());
        $object->setPublished(false);

        try {
            $object->save();
            $return = [
                'success' => true,
                'id' => $object->getId(),
                'type' => $object->getType(),
                'message' => 'success',
            ];
        } catch (\Exception $e) {
            $return = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
        // print_r($return);
        if ($return['success'] == true) {
            return $this->getAction($return['id'], true, '');

        } else {
            return $this->getAction('', true, 'Unable to create user');
        }

    }

    public function getAction($id, $isAdd = false, $message = '')
    {
        // print_r('getAction');
        // exit;

        if ($id != '') {
            $objectId = $id;
            $objectFromDatabase = DataObject\Concrete::getById($objectId);
            if ($objectFromDatabase === null) {
                return 'not found';
            }


            $objectFromDatabase = clone $objectFromDatabase;
            $draftVersion = null;
            $object = $this->getLatestVersion($objectFromDatabase, $draftVersion);

            if (Element\Editlock::isLocked($objectId, 'object')) {
                $message = 'This product is opened by other user. Please try after some time';
                // $message =  $this->getEditLockResponse($objectId, 'object');
            }

            Element\Editlock::lock($objectId, 'object');
            $objectFromVersion = $object !== $objectFromDatabase;
            $objectData = [];
            $objectData['idPath'] = Element\Service::getIdPath($objectFromDatabase);
            $previewGenerator = $objectFromDatabase->getClass()->getPreviewGenerator();
            $linkGeneratorReference = $objectFromDatabase->getClass()->getLinkGeneratorReference();
            $objectData['hasPreview'] = false;
            if ($objectFromDatabase->getClass()->getPreviewUrl() || $linkGeneratorReference || $previewGenerator) {
                $objectData['hasPreview'] = true;
            }

            if ($draftVersion && $objectFromDatabase->getModificationDate() < $draftVersion->getDate()) {
                $objectData['draft'] = [
                    'id' => $draftVersion->getId(),
                    'modificationDate' => $draftVersion->getDate(),
                    'isAutoSave' => $draftVersion->isAutoSave(),
                ];
            }

            $objectData['general'] = [];

            $allowedKeys = ['o_published', 'o_key', 'o_id', 'o_creationDate', 'o_classId', 'o_className', 'o_type', 'o_parentId', 'o_userOwner'];
            foreach ($objectFromDatabase->getObjectVars() as $key => $value) {
                if (in_array($key, $allowedKeys)) {
                    $objectData['general'][$key] = $value;
                }
            }
            $objectData['general']['fullpath'] = $objectFromDatabase->getRealFullPath();
            $objectData['general']['o_locked'] = $objectFromDatabase->isLocked();
            $objectData['general']['php'] = [
                'classes' => array_merge([get_class($objectFromDatabase)], array_values(class_parents($objectFromDatabase))),
                'interfaces' => array_values(class_implements($objectFromDatabase)),
            ];
            $objectData['general']['allowInheritance'] = $objectFromDatabase->getClass()->getAllowInherit();
            $objectData['general']['allowVariants'] = $objectFromDatabase->getClass()->getAllowVariants();
            $objectData['general']['showVariants'] = $objectFromDatabase->getClass()->getShowVariants();
            $objectData['general']['showAppLoggerTab'] = $objectFromDatabase->getClass()->getShowAppLoggerTab();
            $objectData['general']['showFieldLookup'] = $objectFromDatabase->getClass()->getShowFieldLookup();
            if ($objectFromDatabase instanceof DataObject\Concrete) {
                $objectData['general']['linkGeneratorReference'] = $linkGeneratorReference;
                if ($previewGenerator) {
                    $objectData['general']['previewConfig'] = $previewGenerator->getPreviewConfig($objectFromDatabase);
                }
            }

            $objectData['layout'] = $objectFromDatabase->getClass()->getLayoutDefinitions();
            $portalUser = $this->getUser();


            // print_r($portalUser->getPortalUserId());exit;
            $objectVersions = Element\Service::getSafeVersionInfo($objectFromDatabase->getVersions());
            $objectData['versions'] = array_splice($objectVersions, -1, 1);
            $objectData['childdata']['id'] = $objectFromDatabase->getId();
            $objectData['childdata']['data']['classes'] = $this->prepareChildClasses($objectFromDatabase->getDao()->getClasses());
            $objectData['childdata']['data']['general'] = $objectData['general'];
            $allowedKeys = ['o_modificationDate', 'o_userModification'];
            foreach ($object->getObjectVars() as $key => $value) {
                if (in_array($key, $allowedKeys)) {
                    $objectData['general'][$key] = $value;
                }
            }

            $this->getDataForObject($object, $objectFromVersion);
            $objectData['data'] = $this->objectData;
            $objectData['metaData'] = $this->metaData;
            $objectData['properties'] = Element\Service::minimizePropertiesForEditmode($object->getProperties());

            // this used for the "this is not a published version" hint
            // and for adding the published icon to version overview
            $objectData['general']['versionDate'] = $objectFromDatabase->getModificationDate();
            $objectData['general']['versionCount'] = $objectFromDatabase->getVersionCount();
            $currentLayoutId = 5;
            $event = new GenericEvent($this, [
                'data' => $objectData,
                'object' => $object,
            ]);
            $eventDispatcher = new EventDispatcher();
            $eventDispatcher->dispatch($event, AdminEvents::OBJECT_GET_PRE_SEND_DATA);
            DataObject\Service::enrichLayoutDefinition($objectData['layout'], $object);

            $data = $event->getArgument('data');
            DataObject\Service::removeElementFromSession('object', $object->getId());

            $layoutArray = json_decode(json_encode($data['layout']), true);
            $this->classFieldDefinitions = json_decode(json_encode($object->getClass()->getFieldDefinitions()), true);
            $this->injectValuesForCustomLayout($layoutArray);
            $data['layout'] = $layoutArray;
            $data['UnitLst'] = $this->unitListAction();
            $data['ObjectBricks'] = $this->objectbrickTreeAction($object->getClassId(), $id, 'Specifications');
            $data['VariantBricks'] = $this->objectbrickTreeAction($object->getClassId(), $id, 'VariantAttributes');
            // return $data;
            // $portalUser = $this->getUser();
            // $portalUserGroup = $this->getUserGroups();
            $userGroups = $portalUser->getGroups();
            // print_r($userGroups);
            $currentUserRole = array();

            foreach ($userGroups as $userGroup) {

                $currentUserRole[] = array('id' => $userGroup->getId(), 'Name' => $userGroup->getKey());

                // exit;
            }

            //print_r($data);
            // exit;
            // print_r($currentUserRole);
            $data['curol'] = $currentUserRole;
            // exit;
            return $this->render('crud/form.html.twig', ['data' => $data, 'isAdd' => $isAdd, $message]);
        } else {
            return $this->render('crud/form.html.twig', ['data' => '', 'isAdd' => $isAdd, 'message' => $message]);
        }
    }

    public function unitListAction()
    {
        $list = new Unit\Listing();
        $list->setOrderKey(['baseunit', 'factor', 'abbreviation']);
        $list->setOrder(['ASC', 'ASC', 'ASC']);


        $result = [];
        $units = $list->getUnits();
        foreach ($units as &$unit) {
            try {
                if ($unit->getAbbreviation()) {
                    $unit->setAbbreviation(
                        \Pimcore\Model\Translation::getByKeyLocalized(
                            $unit->getAbbreviation(),
                            Translation::DOMAIN_ADMIN,
                            true,
                            true
                        )
                    );
                }
                if ($unit->getLongname()) {
                    $unit->setLongname(
                        \Pimcore\Model\Translation::getByKeyLocalized(
                            $unit->getLongname(),
                            Translation::DOMAIN_ADMIN,
                            true,
                            true
                        )
                    );
                }
                $result[] = $unit->getObjectVars();
            } catch (\Exception $e) {
                // nothing to do ...
            }
        }
        $return = array('data' => $result, 'success' => true, 'total' => $list->getTotalCount());
        return $return;
    }


    private function injectValuesForCustomLayout(array &$layout): void
    {
        foreach ($layout['children'] as &$child) {
            if ($child['datatype'] === 'layout') {
                $this->injectValuesForCustomLayout($child);
            } else {
                foreach ($this->classFieldDefinitions[$child['name']] as $key => $value) {
                    if (array_key_exists($key, $child) && ($child[$key] === null || $child[$key] === '' || (is_array($child[$key]) && empty($child[$key])))) {
                        $child[$key] = $value;
                    }
                }
            }
        }

        //TODO remove in Pimcore 11
        if (isset($layout['childs'])) {
            foreach ($layout['childs'] as &$child) {
                if ($child['datatype'] === 'layout') {
                    $this->injectValuesForCustomLayout($child);
                } else {
                    foreach ($this->classFieldDefinitions[$child['name']] as $key => $value) {
                        if (array_key_exists($key, $child) && ($child[$key] === null || $child[$key] === '' || (is_array($child[$key]) && empty($child[$key])))) {
                            $child[$key] = $value;
                        }
                    }
                }
            }
        }
    }

    // /**
    //  * @Route("/create-product", name="create_product")
    //  */
    // public function create_productAction(Request $request)
    // {

    //     try {
    //         $myObject = new Workflow;

    //         //In this case, Static ParentId is declared to be dynamic.
    //         $myObject->setParentId(151);
    //         $myObject->setKey($request->get('ProductName'));

    //         //Product Details & Media

    //         $myObject->setProductName($request->get('ProductName'));
    //         $myObject->setSKU($request->get('SKU'));
    //         $myObject->setUPC($request->get('UPC'));
    //         $myObject->setGlobalTradeItemNumber($request->get('GlobalTradeItemNumber'));
    //         //$myObject->setAssetAdvanced($request->get('AssetAdvanced'));
    //         //$myObject->setAssetsGallery($request->get('AssetsGallery'));
    //         //$myObject->setCantoLink($request->get('CantoLink'));
    //         //$myObject->setBrand($request->get('Brand'));
    //         //$myObject->setCategory($request->get('Category'));

    //         //Product Feature

    //         //$myObject->setDescriptionField($request->get('DescriptionField'));
    //         $myObject->setDescription($request->get('Description'));

    //         //Pricing

    //         $myObject->setPriceType($request->get('PriceType'));
    //         $myObject->setPrice($request->get('Price'));
    //         $myObject->setAvailability($request->get('Availability'));
    //         $myObject->setItemStatus($request->get('ItemStatus'));

    //         //Creating and saving object
    //         $myObject->setPublished(1);

    //         $myObject->save();
    //     } catch (Exception $e) {
    //         //display custom message
    //     throw new \RuntimeException("Failed to create product: " . $e->getMessage());
    //     }

    //     return new Response("Product created Sucessfully");
    // }



    //Fetch Object Bricks list
    public function objectBricksListAction()
    {
        $bricks = new DataObject\Objectbrick\Definition\Listing();
        $ab = $bricks->load();
        $response = []; // Initialize an empty array

        foreach ($ab as $object) {
            $response[] = [
                "objectBricks" => $object->getKey()
            ];
        }

        return $this->json(["success" => true, "data" => $response]);
    }


    //Fetch Object Bricks Keys by Object Bricks Name
    public function getObjectBrickColumnsAction()
    {
        $objectBrickName = 'Lights';
        $tableName = 'object_brick_query_' . $objectBrickName . '_AllProducts';
        $columns = [];

        $query = "SHOW COLUMNS FROM $tableName";
        $result = $this->getDoctrine()->getManager()->getConnection()->fetchAllAssociative($query);

        foreach ($result as $row) {
            $columnName = $row['Field'];

            if ($columnName !== 'o_id' && $columnName !== 'fieldname') {
                $columns[$columnName] = null;
            }
        }
        return $this->json(["success" => true, "data" => $columns]);
    }

    // Fetch object bricks based on Data Object Id
    public function object_bricks_listAction()
    {
        $product = DataObject\Products::getById(136);
        $data['id'] = $product->getID();
        $data['title'] = $product->getProductName();
        $data['SKU'] = $product->getSKU();
        $data['UPC'] = $product->getUPC();
        $data['Global Trade Item Number'] = $product->getGlobalTradeItemNumber();
        $data['AssetAdvanced'] = $product->getAssetAdvanced();

        $specs = $product->getSpecifications(); // The datas is returned
        // print_r($specs);
        $apparel = $specs->getApparel();
        // print_r($apparel);
        $data['material'] = $apparel->getMaterial();
        $data['color'] = $apparel->getColor();
        $data['logo'] = $apparel->getLogo();
        $data['neckline'] = $apparel->getNeckline();
        $data['materialSpecs'] = $apparel->getMaterialSpecs();

        // print_r($specs);

        return new Response(json_encode($data));
    }

    protected function getLatestVersion(DataObject\Concrete $object, &$draftVersion = null): ?DataObject\Concrete
    {
        $portalUser = $this->getUser();
        $latestVersion = $object->getLatestVersion($portalUser->getpimcoreUser());
        // $allversions = $object->getVersions($portalUser->getpimcoreUser());
        // var_dump($allversions);
        if ($latestVersion) {
            $latestObj = $latestVersion->loadData();
            if ($latestObj instanceof DataObject\Concrete) {
                $draftVersion = $latestVersion;

                return $latestObj;
            }
        }

        return $object;
    }

    protected function prepareChildClasses(array $classes): array
    {
        $reduced = [];
        foreach ($classes as $class) {
            $reduced[] = [
                'id' => $class->getId(),
                'name' => $class->getName(),
                'inheritance' => $class->getAllowInherit(),
            ];
        }

        return $reduced;
    }

    /**
     * @param DataObject\Concrete $object
     * @param bool $objectFromVersion
     */
    private function getDataForObject(DataObject\Concrete $object, $objectFromVersion = false)
    {
        foreach ($object->getClass()->getFieldDefinitions(['object' => $object]) as $key => $def) {
            $this->getDataForField($object, $key, $def, $objectFromVersion);
        }
    }

    /**
     * gets recursively attribute data from parent and fills objectData and metaData
     *
     * @param DataObject\Concrete $object
     * @param string $key
     * @param DataObject\ClassDefinition\Data $fielddefinition
     * @param bool $objectFromVersion
     * @param int $level
     */
    private function getDataForField($object, $key, $fielddefinition, $objectFromVersion, $level = 0)
    {
        $parent = DataObject\Service::hasInheritableParentObject($object);
        $getter = 'get' . ucfirst($key);

        // Editmode optimization for lazy loaded relations (note that this is just for AbstractRelations, not for all
        // LazyLoadingSupportInterface types. It tries to optimize fetching the data needed for the editmode without
        // loading the entire target element.
        // ReverseObjectRelation should go in there anyway (regardless if it a version or not),
        // so that the values can be loaded.
        if (
            (!$objectFromVersion && $fielddefinition instanceof AbstractRelations)
            || $fielddefinition instanceof ReverseObjectRelation
        ) {
            $refId = null;

            if ($fielddefinition instanceof ReverseObjectRelation) {
                $refKey = $fielddefinition->getOwnerFieldName();
                $refClass = DataObject\ClassDefinition::getByName($fielddefinition->getOwnerClassName());
                if ($refClass) {
                    $refId = $refClass->getId();
                }
            } else {
                $refKey = $key;
            }

            $relations = $object->getRelationData($refKey, !$fielddefinition instanceof ReverseObjectRelation, $refId);

            if ($fielddefinition->supportsInheritance() && empty($relations) && !empty($parent)) {
                $this->getDataForField($parent, $key, $fielddefinition, $objectFromVersion, $level + 1);
            } else {
                $data = [];

                if ($fielddefinition instanceof DataObject\ClassDefinition\Data\ManyToOneRelation) {
                    if (isset($relations[0])) {
                        $data = $relations[0];
                        $data['published'] = (bool) $data['published'];
                    } else {
                        $data = null;
                    }
                } elseif (
                    ($fielddefinition instanceof DataObject\ClassDefinition\Data\OptimizedAdminLoadingInterface)
                    || ($fielddefinition instanceof ManyToManyObjectRelation && !$fielddefinition->getVisibleFields() && !$fielddefinition instanceof DataObject\ClassDefinition\Data\AdvancedManyToManyObjectRelation)
                ) {
                    foreach ($relations as $rkey => $rel) {
                        $index = $rkey + 1;
                        $rel['fullpath'] = $rel['path'];
                        $rel['classname'] = $rel['subtype'];
                        $rel['rowId'] = $rel['id'] . AbstractRelations::RELATION_ID_SEPARATOR . $index . AbstractRelations::RELATION_ID_SEPARATOR . $rel['type'];
                        $rel['published'] = (bool) $rel['published'];
                        $data[] = $rel;
                    }
                } else {
                    $fieldData = $object->$getter();
                    $data = $fielddefinition->getDataForEditmode($fieldData, $object, ['objectFromVersion' => $objectFromVersion]);
                }
                $this->objectData[$key] = $data;
                $this->metaData[$key]['objectid'] = $object->getId();
                $this->metaData[$key]['inherited'] = $level != 0;
            }
        } else {
            $fieldData = $object->$getter();
            $isInheritedValue = false;

            if ($fielddefinition instanceof DataObject\ClassDefinition\Data\CalculatedValue) {
                $fieldData = new DataObject\Data\CalculatedValue($fielddefinition->getName());
                $fieldData->setContextualData('object', null, null, null, null, null, $fielddefinition);
                $value = $fielddefinition->getDataForEditmode($fieldData, $object, ['objectFromVersion' => $objectFromVersion]);
            } else {
                $value = $fielddefinition->getDataForEditmode($fieldData, $object, ['objectFromVersion' => $objectFromVersion]);
            }

            // following some exceptions for special data types (localizedfields, objectbricks)
            if ($value && ($fieldData instanceof DataObject\Localizedfield || $fieldData instanceof DataObject\Classificationstore)) {
                // make sure that the localized field participates in the inheritance detection process
                $isInheritedValue = $value['inherited'];
            }
            if ($fielddefinition instanceof DataObject\ClassDefinition\Data\Objectbricks && is_array($value)) {
                // make sure that the objectbricks participate in the inheritance detection process
                foreach ($value as $singleBrickData) {
                    if (!empty($singleBrickData['inherited'])) {
                        $isInheritedValue = true;
                    }
                }
            }

            if ($fielddefinition->isEmpty($fieldData) && !empty($parent)) {
                $this->getDataForField($parent, $key, $fielddefinition, $objectFromVersion, $level + 1);
                // exception for classification store. if there are no items then it is empty by definition.
                // consequence is that we have to preserve the metadata information
                // see https://github.com/pimcore/pimcore/issues/9329
                if ($fielddefinition instanceof DataObject\ClassDefinition\Data\Classificationstore && $level == 0) {
                    $this->objectData[$key]['metaData'] = $value['metaData'] ?? [];
                    $this->objectData[$key]['inherited'] = true;
                }
            } else {
                $isInheritedValue = $isInheritedValue || ($level != 0);
                $this->metaData[$key]['objectid'] = $object->getId();

                $this->objectData[$key] = $value;
                $this->metaData[$key]['inherited'] = $isInheritedValue;

                if ($isInheritedValue && !$fielddefinition->isEmpty($fieldData) && !$fielddefinition->supportsInheritance()) {
                    $this->objectData[$key] = null;
                    $this->metaData[$key]['inherited'] = false;
                    $this->metaData[$key]['hasParentValue'] = true;
                }
            }
        }
    }

    public function objectbrickTreeAction($classId, $objectId, $fieldname)
    {
        $list = new DataObject\Objectbrick\Definition\Listing();
        $list = $list->load();

        $forObjectEditor = 1;

        $context = null;
        $layoutDefinitions = [];
        $groups = [];
        $definitions = [];
        $fieldname = null;
        $className = null;

        $object = DataObject\Concrete::getById($objectId);

        if ($classId != "" && $fieldname != "") {
            $classDefinition = DataObject\ClassDefinition::getById($classId);
            $className = $classDefinition->getName();
        }

        foreach ($list as $item) {
            if ($forObjectEditor) {
                $context = [
                    'containerType' => 'objectbrick',
                    'containerKey' => $item->getKey(),
                    'outerFieldname' => $fieldname,
                ];
            }
            if ($classId != "" && $fieldname != "") {
                $keep = false;
                $clsDefs = $item->getClassDefinitions();
                if (!empty($clsDefs)) {
                    foreach ($clsDefs as $cd) {
                        if ($cd['classname'] == $className && $cd['fieldname'] == $fieldname) {
                            $keep = true;

                            continue;
                        }
                    }
                }
                if (!$keep) {
                    continue;
                }
            }

            if ($item->getGroup()) {
                if (!isset($groups[$item->getGroup()])) {
                    $groups[$item->getGroup()] = [
                        'id' => 'group_' . $item->getKey(),
                        'text' => htmlspecialchars($item->getGroup()),
                        'expandable' => true,
                        'leaf' => false,
                        'allowChildren' => true,
                        'iconCls' => 'pimcore_icon_folder',
                        'group' => $item->getGroup(),
                        'children' => [],
                    ];
                }
                if ($forObjectEditor) {
                    $layoutId = 0;
                    $itemLayoutDefinitions = null;
                    if ($layoutId) {
                        $layout = DataObject\ClassDefinition\CustomLayout::getById($layoutId . '.brick.' . $item->getKey());
                        if ($layout instanceof DataObject\ClassDefinition\CustomLayout) {
                            $itemLayoutDefinitions = $layout->getLayoutDefinitions();
                        }
                    }

                    if ($itemLayoutDefinitions === null) {
                        $itemLayoutDefinitions = $item->getLayoutDefinitions();
                    }

                    DataObject\Service::enrichLayoutDefinition($itemLayoutDefinitions, $object, $context);

                    $layoutDefinitions[$item->getKey()] = $itemLayoutDefinitions;
                }
                $groups[$item->getGroup()]['children'][] =
                    [
                        'id' => $item->getKey(),
                        'text' => $item->getKey(),
                        'title' => $item->getTitle(),
                        'key' => $item->getKey(),
                        'leaf' => true,
                        'iconCls' => 'pimcore_icon_objectbricks',
                    ];
            } else {
                if ($forObjectEditor) {
                    $layout = $item->getLayoutDefinitions();

                    $currentLayoutId = 5;

                    //. $user = $this->getAdminUser();
                    // if ($currentLayoutId == -1 && $user->isAdmin()) {
                    //    DataObject\Service::createSuperLayout($layout);
                    // } elseif ($currentLayoutId) {
                    $customLayout = DataObject\ClassDefinition\CustomLayout::getById($currentLayoutId . '.brick.' . $item->getKey());
                    if ($customLayout instanceof DataObject\ClassDefinition\CustomLayout) {
                        $layout = $customLayout->getLayoutDefinitions();
                    }
                    //   }

                    DataObject\Service::enrichLayoutDefinition($layout, $object, $context);

                    $layoutDefinitions[$item->getKey()] = $layout;
                }
                $definitions[] = [
                    'id' => $item->getKey(),
                    'text' => $item->getKey(),
                    'title' => $item->getTitle(),
                    'key' => $item->getKey(),
                    'leaf' => true,
                    'iconCls' => 'pimcore_icon_objectbricks',
                ];
            }
        }

        foreach ($groups as $group) {
            $definitions[] = $group;
        }

        $event = new GenericEvent($this, [
            'list' => $definitions,
            'objectId' => $objectId,
        ]);

        $definitions = $event->getArgument('list');
        $return = array('layoutDefinitions' => $layoutDefinitions);
        return $return;
    }

    /**
     * @Route("/send_groupmessage", name="sendToGroup", methods={"GET"})
     */
    public function sendToGroup(
        NotificationService $notificationService,
        int $groupId,
        int $fromUser,
        string $title,
        string $message,
        $element = null,

    ) {
        // print_r ($message); exit;
        $from = $fromUser;
        $to = $groupId;
        $groupObject = DataObject\Concrete::getById((int) $to);
        $portalUsers = DataObject\PortalUser::getList();
        $portalUsers->setCondition("object_portaluser.groups LIKE ?", "%$to%");
        $portalUsers->load();

        $sentUser = array();

        foreach ($portalUsers as $portalUser) {

            if (!in_array($portalUser->getpimcoreUser(), $sentUser)) {
                array_push($sentUser, $portalUser->getpimcoreUser());
                $notificationService->sendToUser(
                    $portalUser->getpimcoreUser(), // Admin user ID
                    $from, // System user ID (replace with the actual system user ID)
                    $title, // Notification title
                    $message,
                    $element
                );
                // print_r ($portalUser); exit;

            }
        }

    }


    /**
     * @Route("/workflow_comments/to/{selectedVersionId}/from/{currentVersion}", name="workflow_comments", methods={"POST"})
     */
    public function saveComments(Request $request, $selectedVersionId, $currentVersion)
    {
        print_r ($selectedVersionId.'aaaaaaaaa');
        try {
            $version1 = \Pimcore\Model\Version::getById($currentVersion);
            // $object1 = $version1->loadData();

            $version2 = \Pimcore\Model\Version::getById($selectedVersionId);
            // $object2 = $version2->loadData();

            $requester = User::getById($version2->getUserId());
            $requesterName = $requester->getFirstname() . ' ' . $requester->getLastname();

            $portalUser = $this->getUser();
            $approver = User::getById($portalUser->getpimcoreUser());
            $approverName = $approver->getFirstname() . ' ' . $approver->getLastname();

            $productId = $version1->getCid();
            $comments = $request->get('comment');
            $status = $request->get('status');
            print_r ($status); //exit;
            if ($status == 0) {
                // echo "Helooooo"; exit;
            $this->getProductVersionFiles($productId, $selectedVersionId); // declined version data
            // $this -> getProductVersionFiles($productId, $currentVersion); // approved version data

            // echo "  selectId-".$selectedVersionId, "  currentId-".$currentVersion, "  reqName-".$requesterName, "  appName-".$approverName, "  status-".$request->get('status'), " comments-".$request->get('comment'), "   Product id - ".$productId; //exit;

            $connection = $this->getDoctrine()->getConnection();
            $currentDateTime = date('Y-m-d H:i:s');
            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder
                ->insert('workflow_comments')
                ->values([
                    'product_id' => ':product_id',
                    'last_published_version' => ':published_version',
                    'new_draft_version' => ':draft_version',
                    'requester_name' => ':requester_name',
                    'approver_name' => ':approver_name',
                    'comment' => ':comment',
                    'created_date' => ':created_date',
                    'modified_date' => ':modified_date',
                    'status' => ':status'
                ])
                ->setParameter('product_id', $productId)
                ->setParameter('published_version', $currentVersion)
                ->setParameter('draft_version', $selectedVersionId)
                ->setParameter('requester_name', $requesterName)
                ->setParameter('approver_name', $approverName)
                ->setParameter('comment', $comments)
                ->setParameter('created_date', $currentDateTime)
                ->setParameter('modified_date', $currentDateTime)
                ->setParameter('status', $status);

            $queryBuilder->execute();
        }
            return new JsonResponse(['success' => true]);
        } catch (DBALException $e) {
            return new JsonResponse(['success' => false, 'error' => 'Database error occurred.']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => 'An unexpected error occurred.']);
        }
    }

    /**
     * @Route("/version_data/product_id/{productId}/version_id/{versionId}", name="version_data")
     */
    function getProductVersionFiles($productId, $versionId)
    {
        print_r ($versionId.'bbbbbbbb'); 

        $directoryBase = intval($productId / 10000) * 10000;
        $dynamicDirectory = 'g' . $directoryBase;

        $fullPath = '/var/www/pimcore/var/versions/object/' . $dynamicDirectory . '/' . $productId;
        $destinationPath = '/var/www/pimcore/var/versions/declinedversion';
        $result = [];

        if (is_dir($fullPath)) {
            if ($handle = opendir($fullPath)) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != "..") {
                        $filePath = $fullPath . DIRECTORY_SEPARATOR . $entry;
                        if (is_file($filePath) && is_readable($filePath)) {
                            $content = file_get_contents($filePath);
                            if (strpos($entry, $versionId) !== false) {
                                $result[$entry] = $content;

                                $destinationDynamicDir = $destinationPath . "/" . $dynamicDirectory . "/" . $productId;
                                if (!is_dir($destinationDynamicDir)) {
                                    mkdir($destinationDynamicDir, 0755, true);
                                }
                                $destinationFilePath = $destinationDynamicDir . DIRECTORY_SEPARATOR . $entry;
                                copy($filePath, $destinationFilePath);
                            }
                        }
                    }
                }
                closedir($handle);
            }
        }
        // print_r($result); exit;
    }

    /**
     * @Route("/declined_version_data/product_id/{productId}", name="declined_version_data")
     */
    function getDeclinedVersionFiles($productId)
    {

        $query = Db::get()->prepare("SELECT * FROM workflow_comments WHERE product_id = ? ORDER BY id DESC LIMIT 50");
        $query->execute([$productId]);
        $versionData = $query->fetchAll();
        $declinedVersionData = null;

        foreach ($versionData as $record) {
            if ($record['new_draft_version'] == 319843) { // remove once testing completed !!!

                $newDraftVersionId = $record['new_draft_version'];

                $directoryBase = intval($productId / 10000) * 10000;
                $dynamicDirectory = 'g' . $directoryBase;
                $versionFilePath = "/var/versions/declinedversion/{$dynamicDirectory}/{$productId}/{$newDraftVersionId}";
                $dirPath = dirname(__DIR__, 2) . "/var/versions/declinedversion/$dynamicDirectory/$productId/";

                if (is_dir($dirPath)) {
                    foreach (scandir($dirPath) as $file) {
                        if ($file !== '.' && $file !== '..' && strpos($file, (string) $newDraftVersionId) !== false) {
                            $declinedVersionData = file_get_contents($dirPath . DIRECTORY_SEPARATOR . $file);
                        }
                    }
                }
            }
        }

        // print_r($declinedVersionData);
        if ($declinedVersionData) {
            // echo "------123";
            $declinedVersionObject = unserialize($declinedVersionData);
            // print_r($declinedVersionObject);

            return $this->render(
                'crud/previewVersion.html.twig',
                [
                    'object' => $declinedVersionObject,
                    'versionNote' => "test",
                    // 'validLanguages' => Tool::getValidLanguages(),
                ]
            );

            // if ($declinedVersionObject instanceof \Pimcore\Model\Version) {

            //     $dataObject = $declinedVersionObject->loadData();
            //     if ($dataObject instanceof MyDataObject) {
            //         print_r($dataObject);
            //     }
            // }
        }

        echo "No";
        exit;
    }
}
