<?php

namespace S3Storage\Package;

use Page;
use PageType;
use SinglePage;
use PageTemplate;
use Concrete\Core\Page\Type\PublishTarget\Type\Type as PublishTargetType;

trait PageTrait
{
    /**
     * Add a Specific Page
     *
     * @param string|int        $pathOrCID
     * @param string            $name
     * @param string            $description
     * @param string            $type
     * @param string            $template
     * @param string|int|object $parent
     * @param object            $pkg
     * @param string            $handle
     *
     * @return object Page Object
     */
    protected function addPage(
        $pathOrCID,
        string $name,
        string $description,
        string $type,
        string $template,
        $parent,
        $pkg,
        ?string $handle,
    ) {
        //Get Page if it's already created
        if (is_int($pathOrCID)) {
            $page = Page::getByID($pathOrCID);
        } else {
            $page = Page::getByPath($pathOrCID);
        }
        if ($page->isError() && $page->getError() == COLLECTION_NOT_FOUND) {
            //Get Page Type and Templates from their handles
            $pageType = PageType::getByHandle($type);
            $pageTemplate = PageTemplate::getByHandle($template);

            //Get parent, depending on what format parent is passed in
            if (is_object($parent)) {
                $parent = $parent;
            } elseif (is_int($parent)) {
                $parent = Page::getById($parent);
            } else {
                $parent = Page::getByPath($parent);
            }
            //Get package
            $pkgID = $pkg->getPackageID();

            //Create Page
            $page = $parent->add($pageType, [
                'cName' => $name,
                'cHandle' => $handle,
                'cDescription' => $description,
                'pkgID' => $pkgID,
                'cHandle' => $handle,
            ], $pageTemplate);
        }

        return $page;
    }

    /**
     * Adds a Page Type with an All Publish Target (can publish anywhere)
     *
     * @param string $typeHandle            Page Type Handle
     * @param string $typeName              Page Type Name
     * @param string $defaultTemplateHandle
     * @param string $allowedTemplates      (A|C|X) A for all, C for selected only, X for non-selected only
     * @param array  $templateArray         Array or Iterator of selected templates, see `$allowedTemplates`
     * @param object $pkg
     * @param int    $startingPointCID      CID of optional starting point below which page can be added
     * @param int    $selectorFormFactor    Form factor of page selector
     *
     * @return object Page Type Object
     */
    protected function addPageTypeWithAllPublishTarget(
        string $typeHandle,
        string $typeName,
        string $defaultTemplateHandle,
        string $allowedTemplates,
        array $templateArray,
        $pkg,
        int $startingPointCID = 0,
        int $selectorFormFactor = 0,
    ) {
        $pt = PageType::getByHandle($typeHandle);
        if (!is_object($pt)) {
            $pto = $this->addPageType($typeHandle, $typeName, $defaultTemplateHandle, $allowedTemplates, $templateArray, $pkg);
            $pt = $this->setAllPublishTarget($pto, $startingPointCID, $selectorFormFactor);
        }

        return $pt;
    }

    /**
     * Add a Page Type with a Page Type Publish Target
     *
     * @param string $typeHandle            Page Type Handle
     * @param string $typeName              Page Type Name
     * @param string $defaultTemplateHandle
     * @param string $allowedTemplates      (A|C|X) A for all, C for selected only, X for non-selected only
     * @param array  $templateArray         Array or Iterator of selected templates, see `$allowedTemplates`
     * @param int    $parentPageTypeID      ID of parent Page Type
     * @param object $pkg
     * @param int    $startingPointCID      CID of optional starting point below which page can be added
     * @param int    $selectorFormFactor    Form factor of page selector
     *
     * @return object Page Type Object
     */
    protected function addPageTypeWithPageTypePublishTarget(
        string $typeHandle,
        string $typeName,
        string $defaultTemplateHandle,
        string $allowedTemplates,
        array $templateArray,
        int $parentPageTypeID,
        $pkg,
        int $startingPointCID = 0,
        int $selectorFormFactor = 0,
    ) {
        $pt = PageType::getByHandle($typeHandle);
        if (!is_object($pt)) {
            $pto = $this->addPageType($typeHandle, $typeName, $defaultTemplateHandle, $allowedTemplates, $templateArray, $pkg);
            $pt = $this->setPageTypePublishTarget($pto, $parentPageTypeID, $startingPointCID, $selectorFormFactor);
        }

        return $pt;
    }

    /**
     * Add a Page Type with a Parent Page Publish Target
     *
     * @param string $typeHandle            Page Type Handle
     * @param string $typeName              Page Type Name
     * @param string $defaultTemplateHandle
     * @param string $allowedTemplates      (A|C|X) A for all, C for selected only, X for non-selected only
     * @param array  $templateArray         Array or Iterator of selected templates, see `$allowedTemplates`
     * @param int    $parentPageCID         Parent Page CID
     * @param object $pkg
     *
     * @return object PageType Object
     */
    protected function addPageTypeWithParentPagePublishTarget(
        string $typeHandle,
        string $typeName,
        string $defaultTemplateHandle,
        string $allowedTemplates,
        array $templateArray,
        int $parentPageCID,
        $pkg,
    ) {
        $pt = PageType::getByHandle($typeHandle);
        if (!is_object($pt)) {
            $pto = $this->addPageType($typeHandle, $typeName, $defaultTemplateHandle, $allowedTemplates, $templateArray, $pkg);
            $pt = $this->setParentPagePublishTarget($pto, $parentPageCID);
        }

        return $pt;
    }

    /**
     * Add New Page Type
     *
     * @param string $typeHandle            New Type Handle
     * @param string $typeName              New Type Name
     * @param string $defaultTemplateHandle
     * @param string $allowedTemplates      (A|C|X) A for all, C for selected only, X for non-selected only
     * @param array  $templateArray         Array or Iterator of selected templates, see `$allowedTemplates`
     * @param object $pkg
     *
     * @return object Page Type Object
     */
    protected function addPageType(
        string $typeHandle,
        string $typeName,
        string $defaultTemplateHandle,
        string $allowedTemplates,
        array $templateArray,
        $pkg,
    ) {
        //Get required objects (these can be handles after 8)
        $defaultTemplate = PageTemplate::getByHandle($defaultTemplateHandle);
        $allowedTemplateArray = [];
        foreach ($templateArray as $handle) {
            $allowedTemplateArray[] = PageTemplate::getByHandle($handle);
        }

        $data = [
            'handle' => $typeHandle,
            'name' => $typeName,
            'defaultTemplate' => $defaultTemplate,
            'allowedTemplates' => $allowedTemplates,
            'templates' => $allowedTemplateArray,
        ];

        $pt = PageType::getByHandle($typeHandle);
        if (is_object($pt)) {
            $pt->update($data);
            return $pt;
        }
        $pt = PageType::add($data, $pkg);
        return $pt;

    }

    /**
     * Set All Pages Publish Target for Page Type
     *
     * @param PageType $pageTypeObject     Page Type Object
     * @param int      $startingPointCID   CID of page to be underneath, or 0 for any page
     * @param int      $selectorFormFactor 1 for in page sitemap, 0 for popup sitemap
     *
     * @return object Page Type Object
     */
    protected function setAllPublishTarget(
        PageType $pageTypeObject,
        int $startingPointCID = 0,
        int $selectorFormFactor = 0,
    ) {
        $allTarget = PublishTargetType::getByHandle('all');
        $configuredTarget = $allTarget->configurePageTypePublishTarget(
            $pageTypeObject,
            [
                'selectorFormFactorAll' => $selectorFormFactor, // this is the form factor of the page selector. null or false is the standard sitemap popup. 1 or true would be the in page sitemap
                'startingPointPageIDall' => ($startingPointCID), // If you only want this available below a certain explicit page, but anywhere nested under that page, set this page id. null or false sets this to anywhere
            ],
        );
        $pageTypeObject->setConfiguredPageTypePublishTargetObject($configuredTarget);

        return $pageTypeObject;
    }

    /**
     * Set Page Type Publish Target for Page Type
     *
     * @param PageType $pageTypeObject     Page Type Object
     * @param int      $parentPageTypeID   Parent Page Type ID
     * @param int      $startingPointCID   CID of page to be underneath, or 0 for any page
     * @param int      $selectorFormFactor 1 for in page sitemap, 0 for popup sitemap
     *
     * @return object Page Type Object
     */
    protected function setPageTypePublishTarget(
        PageType $pageTypeObject,
        int $parentPageTypeID,
        int $startingPointCID = 0,
        int $selectorFormFactor = 0,
    ) {
        $typeTarget = PublishTargetType::getByHandle('page_type');
        $configuredTypeTarget = $typeTarget->configurePageTypePublishTarget(
            $pageTypeObject, //the one being set up, NOT the target one
            [
                'ptID' => $parentPageTypeID,
                'startingPointPageIDPageType' => $startingPointCID, // this is the form factor of the page selector. null or false is the standard sitemap popup. 1 or true would be the in page sitemap
                'selectorFormFactorPageType' => $selectorFormFactor, // If you only want this available below a certain explicit page, but anywhere nested under that page, set this page id. null or false sets this to anywhere
            ],
        );
        $pageTypeObject->setConfiguredPageTypePublishTargetObject($configuredTypeTarget);

        return $pageTypeObject;
    }

    /**
     * Set Parent Page Publish Target for Page Type
     *
     * @param PageType $pageTypeObject Page Type Object
     * @param int      $parentPageCID  Parent Page CID
     *
     * @return PageType Page Type Object
     */
    protected function setParentPagePublishTarget(PageType $pageTypeObject, int $parentPageCID)
    {
        $parentTarget = PublishTargetType::getByHandle('parent_page');
        $configuredParentTarget = $parentTarget->configurePageTypePublishTarget(
            $pageTypeObject,
            [
                'CParentID' => $parentPageCID,
            ],
        );
        $pageTypeObject->setConfiguredPageTypePublishTargetObject($configuredParentTarget);

        return $pageTypeObject;
    }

    /**
     * Add Single Page
     *
     * @param string $path        Page Path
     * @param object $pkg
     * @param string $name        Single Page Name
     * @param string $description Single Page Description
     *
     * @return object Single Page Object
     */
    protected function addSinglePage(string $path, $pkg, string $name = '', string $description = '')
    {
        //Install single page
        $sp = Page::getByPath($path);
        if ($sp->isError() && $sp->getError() == COLLECTION_NOT_FOUND) {
            $sp = SinglePage::add($path, $pkg);
        }

        //Set name and description
        if (!empty($name) || !empty($description)) {
            $data = [];
            if (!empty($name)) {
                $data['cName'] = $name;
            }
            if (!empty($description)) {
                $data['cDescription'] = $description;
            }
            $sp->update($data);
        }

        return $sp;
    }

    /**
     * Add a Page Template
     *
     * @param string                        $handle
     * @param string                        $name
     * @param \Concrete\Core\Entity\Package $pkg
     * @param string                        $icon
     *
     * @return \Concrete\Core\Entity\Page\Template
     */
    protected function addPageTemplate(string $handle, string $name, \Concrete\Core\Entity\Package $pkg, string $icon = 'landing.png'): \Concrete\Core\Entity\Page\Template
    {
        $template = PageTemplate::getByHandle($handle);
        if (!is_object($template)) {
            $template = PageTemplate::add($handle, $name, $icon, $pkg);
        }

        return $template;
    }
}
