<?php
namespace Craft;

class CpNav_NavService extends BaseApplicationComponent
{
	public function getAllNavs($indexBy = null)
	{
		$navRecords = CpNav_NavRecord::model()->ordered()->findAll();
		return CpNav_NavModel::populateModels($navRecords, $indexBy);
	}
	
	public function getNavsByLayoutId($layoutId, $indexBy = null)
	{
    	$navRecords = CpNav_NavRecord::model()->ordered()->findAllByAttributes(array('layoutId' => $layoutId));
		return CpNav_NavModel::populateModels($navRecords, $indexBy);
	}

	public function getNavById($navId)
	{
		$navRecord = CpNav_NavRecord::model()->findById($navId);

		if ($navRecord) {
			return CpNav_NavModel::populateModel($navRecord);
		}
	}

	public function getDefaultOrUserNavs($forUser = null)
	{
		if ($forUser) {
			$currentUser = craft()->users->getUserById($forUser);
		} else {
	        $currentUser = craft()->userSession->getUser();
		}

		//var_dump($currentUser);
		if (property_exists($currentUser->getContent(), 'controlPanelLayout')) {
	        $userNav = $currentUser->getContent()->controlPanelLayout;
		} else {
	        $userNav = null;
		}

        if ($userNav) {
            // There's a user-specific layout - that needs to be shown
            $allNavs = array();

            foreach ($userNav as $uNav) {
                $globalNav = craft()->cpNav_nav->getNavById($uNav['id']);
                $globalNav->enabled = $uNav['enabled'];
                
                $allNavs[] = $globalNav;
            }
        } else {
            // No user-specific layout set - return the default
            $allNavs = craft()->cpNav_nav->getNavsByLayoutId('1');
        }

        return $allNavs;
	}

	public function reorderNav($navIds)
	{
		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

		try {
			foreach ($navIds as $navOrder => $navId) {
				$navModel = $this->getNavById($navId);
				$navRecord = CpNav_NavRecord::model()->findById($navModel->id);
				$navRecord->order = $navOrder+1;
				$navRecord->save();

				$navModel->order = $navRecord->order;
			}

			if ($transaction !== null) {
				$transaction->commit();
			}
		} catch (\Exception $e) {
			if ($transaction !== null) {
				$transaction->rollback();
			}

			throw $e;
		}

		$navModel = $this->getNavById($navIds[0]);
		return $this->getNavsByLayoutId($navModel->layoutId);
	}

	public function toggleNav($navId, $toggle)
	{
		$navModel = $this->getNavById($navId);
		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

		try {
			$navRecord = CpNav_NavRecord::model()->findById($navModel->id);
			$navRecord->enabled = $toggle;
			$navRecord->save();

			$navModel->enabled = $navRecord->enabled;

			if ($transaction !== null) {
				$transaction->commit();
			}
		} catch (\Exception $e) {
			if ($transaction !== null) {
				$transaction->rollback();
			}

			throw $e;
		}

		return $this->getNavsByLayoutId($navModel->layoutId);
	}

	public function saveNav(CpNav_NavModel $nav)
	{
		$navRecord = CpNav_NavRecord::model()->findById($nav->id);

		$navRecord->currLabel = $nav->currLabel;
		$navRecord->prevUrl = ($nav->prevUrl) ? $nav->prevUrl : $nav->url;
		$navRecord->url = $nav->url;

		$navRecord->save();

		$nav->currLabel = $navRecord->getAttribute('currLabel');

		return $nav;
	}

	public function createNav($value, $manual = false)
	{
		$navRecord = new CpNav_NavRecord();

		$navRecord->layoutId = $value['layoutId'];
		$navRecord->handle = $value['handle'];
		$navRecord->currLabel = $value['label'];
		$navRecord->prevLabel = $value['label'];
		$navRecord->enabled = '1';
		$navRecord->url = $value['url'];
		$navRecord->prevUrl = $value['url'];
		$navRecord->order = array_key_exists('order', $value) ? $value['order'] : '99';
		$navRecord->manualNav = $manual;

		$navRecord->save();
	}

    public function deleteNav(CpNav_NavModel $nav)
    {
		$navRecord = CpNav_NavRecord::model()->findById($nav->id);

		$navRecord->delete();

		return $this->getNavsByLayoutId($nav->layoutId);
    }

    // Clears out the DB - refreshed on next page load however. Used when restoring to defaults
	public function restoreDefaults($layoutId)
	{
    	$navRecords = CpNav_NavRecord::model()->deleteAll('layoutId = :layoutId', array('layoutId' => $layoutId));

    	//$navRecords->delete();
		//$query = craft()->db->createCommand()->delete('cpnav_navs');
	}



    // Used for migration
    /*public function assignToDefaultLayout() {
    	$navs = $this->getAllNavs();

		foreach ($navs as $navModel) {
			$navRecord = CpNav_NavRecord::model()->findById($navModel->id);

			$navRecord->layoutId = '1';
			$navRecord->save();
		}
    }*/

}






