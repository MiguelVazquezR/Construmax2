<?php

namespace App\Services\Payroll\FaceRecognition\Exceptions;

use RuntimeException;

/**
 * The provider received a photo without a detectable face.
 */
class FaceNotDetectedException extends RuntimeException
{
}
