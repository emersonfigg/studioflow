<?php

namespace App\Enums;

enum LegacyMembershipClassification: string
{
    case PaymentEvidence = 'payment_evidence';
    case PointOfSale = 'point_of_sale';
    case Undetermined = 'undetermined';
    case Inconsistent = 'inconsistent';
}
