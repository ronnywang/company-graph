<?php

class IndexController extends Pix_Controller
{
    public function indexAction()
    {
        $this->view->id = ($_GET['id']);
        if (preg_match('#^[0-9]+$#', $this->view->id)) {
            $unit = Unit::find(intval($this->view->id));
            if (!$unit) {
                return $this->alert("找不到統一編號 {$this->view->id} 的公司", '/');
            }
            $this->view->unit_title = $unit->get('公司名稱')->value;
        } else {
            $this->view->unit_title = strval($this->view->id);
        }
    }

    public function jsonAction()
    {
        return $this->json(GraphLib::getJSONFromID(strval($_GET['id'])));
    }

    public function aboutAction()
    {
    }
}
