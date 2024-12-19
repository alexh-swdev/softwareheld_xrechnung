# SoftwareHeld_Xrechnung

This addon provides the possibility to create XRechnung xml files from the order overview and order details views.

For now, this implementation is in an early stage, quite basic and taylored for my specific use case, but maybe it already helps others.

Feel free to use it at your own risk as long as you don't blame me for anything :)

## Install

If you choose to use it, adjust your `composer.json`
```
(...)
"repositories": [
	{
		"type": "vcs",
		"url": "https://github.com/alexh-swdev/softwareheld_xrechnung.git"
	}
],
(...)
```
Then run
`composer require alexh-swdev/softwareheld_xrechnung --update-no-dev --minimal-changes --optimize-autoloader -vvv`
or similar

## Credits

This module is based on horstoeko/zugferd: https://github.com/horstoeko/zugferd
