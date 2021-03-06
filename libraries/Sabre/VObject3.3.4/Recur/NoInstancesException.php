<?php

namespace Sabre\VObject\Recur;

use Exception;

/**
 * This exception gets thrown when a recurrence iterator produces 0 instances.
 *
 * This may happen when every occurence in a rrule is also in EXDATE.
 *
 * @copyright Copyright (C) 2009-2014 fruux GmbH. All rights reserved.
 * @author Evert Pot (http://evertpot.com/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class NoInstancesException extends Exception {

}
