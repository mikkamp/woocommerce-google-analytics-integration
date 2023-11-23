import { removeAction } from '@wordpress/hooks';
import { addUniqueAction } from '../utils';
import { tracker } from '../tracker';
import { ACTION_PREFIX, NAMESPACE } from '../constants';

addUniqueAction(
	`${ ACTION_PREFIX }-product-list-render`,
	NAMESPACE,
	tracker.attachEvent( 'view_item_list' )
);

/**
 * Temporarily remove all actions for demo purposes.
 */

removeAction( `${ ACTION_PREFIX }-checkout-render-checkout-form`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-checkout-submit`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-checkout-set-email-address`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-checkout-set-phone-number`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-checkout-set-billing-address`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-cart-add-item`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-cart-set-item-quantity`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-cart-remove-item`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-product-view-link`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-product-search`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-product-render`, NAMESPACE );
removeAction( `${ ACTION_PREFIX }-store-notice-create`, NAMESPACE );
