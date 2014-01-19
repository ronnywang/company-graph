<?php

class IndexController extends Pix_Controller
{
    public function indexAction()
    {
        $this->view->id = intval($_GET['id']);
        if ($this->view->id) {
            $this->view->unit = Unit::find(intval($this->view->id));
            if (!$this->view->unit) {
                return $this->alert("找不到統一編號 {$this->view->id} 的公司", '/');
            }
        }
    }

    public function jsonAction()
    {
        return $this->json(GraphLib::getJSONFromID(intval($_GET['id'])));
    }

    public function aboutAction()
    {
    }
}
