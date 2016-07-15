<?php
namespace ToolkitApi;

/**
 * Class ObjectLists
 *
 * @package ToolkitApi
 */
class ObjectLists
{
    private $OBJLLISTREC_SIZE = 30;
    private $ToolkitSrvObj;
    private $TmpUserSpace ;
    private $ErrMessage;

    /**
     * @param ToolkitInterface $ToolkitSrvObj
     */
    public function __construct(ToolkitInterface $ToolkitSrvObj = null)
    {
        if ($ToolkitSrvObj instanceof Toolkit) {
            $this->ToolkitSrvObj = $ToolkitSrvObj;
            return $this;
        } else {
            return false;
        }
    }

    /**
     * @param string $object = *ALL, *name, *generic name
     * @param string $library = *ALL, *name, *generic name
     * @param string $objecttype = *ALL, *type
     * @return array|bool
     * @throws \Exception
     */
    public function getObjectList($object = '*ALL', $library = '*LIBL', $objecttype = '*ALL')
    {
        $ObjName = $object;
        $ObjLib  = $library;
        $ObjType = $objecttype;

        $this->TmpUserSpace = new TmpUserSpace($this->ToolkitSrvObj);

        $UsFullName = $this->TmpUserSpace->getUSFullName();

        $params[] = $this->ToolkitSrvObj->AddParameterChar('in', 20, "User Space Name", 'userspacename', $UsFullName);
        $params[] = $this->ToolkitSrvObj->AddParameterChar('in', 10, "Object name", 'objectname', $ObjName);
        $params[] = $this->ToolkitSrvObj->AddParameterChar('in', 10, "Object library", 'objectlib', $ObjLib);
        $params[] = $this->ToolkitSrvObj->AddParameterChar('in', 10, "Object Type", 'objecttype', $ObjType);
        $this->ToolkitSrvObj->PgmCall(ZSTOOLKITPGM, $this->ToolkitSrvObj->getOption('HelperLib'), $params, NULL, array('func' => 'OBJLST'));

        $ObjList  = $this->TmpUserSpace->ReadUserSpace(1, $this->TmpUserSpace->RetrieveUserSpaceSize());
        $this->TmpUserSpace->DeleteUserSpace();

        unset($this->TmpUserSpace);

        if (trim($ObjList)!='') {
            return (str_split($ObjList, $this->OBJLLISTREC_SIZE));
        } else {
            return false;
        }
    }
}
