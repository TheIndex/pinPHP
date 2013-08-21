<?php

class captchaAction extends frontendAction {

    public function _empty() {
        Image::buildImageVerify(4, 1, 'gif', '50', '24', 'captcha');
    }
}