<?php

namespace App\Media;

use RuntimeException;

// Only explicitly safe diagnostics may be printed by the commands.
class MediaException extends RuntimeException {}
