<?php

namespace App\Services\Payroll\FaceRecognition\Exceptions;

use RuntimeException;

/**
 * The provider received a photo with more than one face, so it cannot decide
 * which one identifies the collaborator.
 */
class MultipleFacesDetectedException extends RuntimeException
{
}
