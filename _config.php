<?php

// override ShortcodeParser with the custom element parser
Object::useCustomClass('ShortcodeParser', 'CustomElementsParser', true);
