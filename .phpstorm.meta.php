<?php

namespace PHPSTORM_META {

    // Reflect
	use Kiri\Context;
	use Kiri\Di\Container;

	override(Container::get(0), map('@'));
	override(Container::create(0), map('@'));
//    override(\Hyperf\Utils\Context::get(0), map('@'));
//    override(\make(0), map('@'));
    override(\di(0), map('@'));
    override(\duplicate(0), map('@'));
    override(Context::getContext(0), map('@'));

}
