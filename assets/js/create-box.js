( function () {
    'use strict';

    if ( 'undefined' === typeof window.CreateBoxData ) {
        return;
    }

    const appData = window.CreateBoxData;
    const payload = appData.payload || {};
    const root = document.querySelector('[data-create-box-root]');

    if ( ! root ) {
        return;
    }

    const selectors = {
        builder: '[data-builder]',
        subtitle: '[data-builder-subtitle]',
        boxes: '[data-boxes]',
        boxTitle: '.create-box__section--boxes .create-box__section-title',
        boxGrid: '[data-box-grid]',
        catalog: '[data-catalog]',
        summary: '[data-summary]',
        summaryItems: '[data-summary-items]',
        summaryBox: '[data-summary-box]',
        summaryTotal: '[data-summary-total]',
        summaryGrand: '[data-summary-grand]',
        selectedList: '[data-selected-list]',
        submitButton: '[data-submit]',
        submitLabel: '[data-submit-label]',
        submitTotal: '[data-submit-total]',
        feedback: '[data-feedback]',
    };

    const elements = {};
    Object.keys( selectors ).forEach( ( key ) => {
        elements[ key ] = root.querySelector( selectors[ key ] );
    } );

    const rules = payload.rules || { min_items: 0, min_total: 0, require_box: false };
    const i18n = payload.i18n || {};
    const currency = payload.currency || { symbol: '', decimals: 2, decimalSeparator: '.', thousandSeparator: ',', format: '%1$s%2$s' };

    const productLookup = new Map();
    const variationLookup = new Map();

    const state = {
        box: null,
        items: new Map(),
        busy: false,
    };

    function formatMoney( amount ) {
        const decimals = Number.isInteger( currency.decimals ) ? currency.decimals : 2;
        const decSep = currency.decimalSeparator || '.';
        const thousandSep = currency.thousandSeparator || ',';
        const format = currency.format || '%1$s%2$s';

        const fixed = ( Number.isFinite( amount ) ? amount : 0 ).toFixed( decimals );
        const parts = fixed.split( '.' );
        let integer = parts[ 0 ];
        const decimal = parts[ 1 ] ? decSep + parts[ 1 ] : '';

        integer = integer.replace( /\B(?=(\d{3})+(?!\d))/g, thousandSep );

        return format
            .replace( '%1$s', currency.symbol || '' )
            .replace( '%2$s', integer + decimal );
    }

    function getItemKey( productId, variationId ) {
        return `${ productId }:${ variationId || 0 }`;
    }

    function storeProductData( product ) {
        if ( ! product || ! product.id ) {
            return;
        }

        productLookup.set( String( product.id ), product );

        if ( Array.isArray( product.variations ) ) {
            const map = new Map();
            product.variations.forEach( ( variation ) => {
                map.set( String( variation.id ), variation );
            } );
            variationLookup.set( String( product.id ), map );
        }
    }

    function setText( el, text ) {
        if ( el ) {
            el.textContent = text || '';
        }
    }

    function setHtml( el, html ) {
        if ( el ) {
            el.innerHTML = html || '';
        }
    }

    function clearElement( el ) {
        if ( el ) {
            while ( el.firstChild ) {
                el.removeChild( el.firstChild );
            }
        }
    }

    function toggleHidden( el, hidden ) {
        if ( el ) {
            el.hidden = Boolean( hidden );
        }
    }

    function showFeedback( message, isError ) {
        if ( ! elements.feedback ) {
            return;
        }

        if ( ! message ) {
            elements.feedback.hidden = true;
            elements.feedback.textContent = '';
            elements.feedback.classList.remove( 'create-box__feedback--error', 'create-box__feedback--success' );
            return;
        }

        elements.feedback.hidden = false;
        elements.feedback.textContent = message;
        elements.feedback.classList.toggle( 'create-box__feedback--error', Boolean( isError ) );
        elements.feedback.classList.toggle( 'create-box__feedback--success', ! isError );
    }

    function renderBoxes() {
        if ( ! elements.boxGrid ) {
            return;
        }

        clearElement( elements.boxGrid );

        const boxes = Array.isArray( payload.boxes ) ? payload.boxes : [];

        if ( elements.boxTitle ) {
            setText( elements.boxTitle, payload.boxes_title || i18n.choose_box || 'Choose your box' );
        }

        if ( ! boxes.length ) {
            const empty = document.createElement( 'p' );
            empty.className = 'create-box__empty';
            empty.textContent = i18n.empty_section || 'No boxes available yet.';
            elements.boxGrid.appendChild( empty );
            return;
        }

        boxes.forEach( ( box ) => {
            storeProductData( box );

            const card = document.createElement( 'article' );
            card.className = 'create-box-card create-box-card--box';
            card.dataset.productId = String( box.id );

            if ( state.box && state.box.productId === box.id ) {
                card.classList.add( 'is-selected' );
            }

            card.innerHTML = `
                <div class="create-box-card__media">
                    <img src="${ box.image || '' }" alt="${ box.name || '' }" loading="lazy" />
                </div>
                <div class="create-box-card__body">
                    <h3 class="create-box-card__title">
                        <a class="create-box-card__link" href="${ box.permalink }" target="_blank" rel="noopener noreferrer">${ box.name }</a>
                    </h3>
                    <div class="create-box-card__price">${ box.price_html || formatMoney( box.price || 0 ) }</div>
                    <div class="create-box-card__actions">
                        <button type="button" class="create-box-card__button" data-box-select>
                            ${ i18n.select_box || 'Select Box' }
                        </button>
                    </div>
                </div>
            `;

            const button = card.querySelector( '[data-box-select]' );
            const isSelected = state.box && state.box.productId === box.id;
            button.textContent = isSelected ? ( i18n.box_selected || 'Selected' ) : ( i18n.select_box || 'Select Box' );
            button.addEventListener( 'click', () => selectBox( box ) );

            elements.boxGrid.appendChild( card );
        } );
    }

    function selectBox( product ) {
        if ( ! product ) {
            return;
        }

        state.box = {
            productId: product.id,
            variationId: 0,
            quantity: 1,
            name: product.name,
            price: product.price || 0,
            priceHtml: product.price_html,
        };

        const cards = elements.boxGrid ? elements.boxGrid.querySelectorAll( '.create-box-card' ) : [];
        cards.forEach( ( card ) => {
            const id = parseInt( card.dataset.productId || '0', 10 );
            const isSelected = id === product.id;
            card.classList.toggle( 'is-selected', isSelected );

            const button = card.querySelector( '[data-box-select]' );
            if ( button ) {
                button.textContent = isSelected ? ( i18n.box_selected || 'Selected' ) : ( i18n.select_box || 'Select Box' );
            }
        } );

        updateSummary();
    }

    function renderSections() {
        if ( ! elements.catalog ) {
            return;
        }

        clearElement( elements.catalog );

        const sections = Array.isArray( payload.sections ) ? payload.sections : [];

        if ( ! sections.length ) {
            const empty = document.createElement( 'p' );
            empty.className = 'create-box__empty';
            empty.textContent = i18n.empty_section || 'No products found in this section yet.';
            elements.catalog.appendChild( empty );
            return;
        }

        sections.forEach( ( section ) => {
            const sectionEl = document.createElement( 'section' );
            sectionEl.className = 'create-box__section create-box__section--products';

            const header = document.createElement( 'div' );
            header.className = 'create-box__section-header';

            const title = document.createElement( 'h3' );
            title.className = 'create-box__section-title';
            title.textContent = section.label;
            header.appendChild( title );

            if ( section.permalink ) {
                const link = document.createElement( 'a' );
                link.className = 'create-box__section-link';
                link.href = section.permalink;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = i18n.view_more || 'View more';
                header.appendChild( link );
            }

            sectionEl.appendChild( header );

            const grid = document.createElement( 'div' );
            grid.className = 'create-box__grid';

            const products = Array.isArray( section.products ) ? section.products : [];

            if ( ! products.length ) {
                const empty = document.createElement( 'p' );
                empty.className = 'create-box__empty';
                empty.textContent = i18n.empty_section || 'No products found in this section yet.';
                grid.appendChild( empty );
            } else {
                products.forEach( ( product ) => {
                    storeProductData( product );
                    grid.appendChild( createProductCard( product ) );
                } );
            }

            sectionEl.appendChild( grid );
            elements.catalog.appendChild( sectionEl );
        } );
    }

    function createProductCard( product ) {
        const card = document.createElement( 'article' );
        card.className = 'create-box-card';
        card.dataset.productId = String( product.id );

        const variationMap = variationLookup.get( String( product.id ) );
        const hasVariations = product.type === 'variable' && variationMap && variationMap.size;

        const image = document.createElement( 'div' );
        image.className = 'create-box-card__media';
        image.innerHTML = `<img src="${ product.image || '' }" alt="${ product.name || '' }" loading="lazy" />`;

        const body = document.createElement( 'div' );
        body.className = 'create-box-card__body';

        const title = document.createElement( 'h4' );
        title.className = 'create-box-card__title';

        if ( product.permalink ) {
            const titleLink = document.createElement( 'a' );
            titleLink.className = 'create-box-card__link';
            titleLink.href = product.permalink;
            titleLink.target = '_blank';
            titleLink.rel = 'noopener noreferrer';
            titleLink.textContent = product.name;
            title.appendChild( titleLink );
        } else {
            title.textContent = product.name;
        }

        const price = document.createElement( 'div' );
        price.className = 'create-box-card__price';
        price.innerHTML = product.price_html || formatMoney( product.price || 0 );

        const actions = document.createElement( 'div' );
        actions.className = 'create-box-card__actions';

        let select = null;

        if ( hasVariations ) {
            select = document.createElement( 'select' );
            select.className = 'create-box-card__select';

            const placeholder = document.createElement( 'option' );
            placeholder.value = '';
            placeholder.textContent = i18n.select_variation || 'Select an option';
            select.appendChild( placeholder );

            variationMap.forEach( ( variation, id ) => {
                if ( ! variation.purchasable || 'outofstock' === variation.stock_status ) {
                    return;
                }

                const option = document.createElement( 'option' );
                option.value = id;
                const priceText = variation.price_html ? variation.price_html.replace( /<[^>]*>/g, '' ) : formatMoney( variation.price || 0 );
                option.textContent = `${ variation.name } – ${ priceText }`;
                select.appendChild( option );
            } );

            actions.appendChild( select );
        }

        const addButton = document.createElement( 'button' );
        addButton.type = 'button';
        addButton.className = 'create-box-card__button';
        addButton.textContent = i18n.add_to_box || 'Add to the Box';

        if ( ! product.purchasable || 'outofstock' === product.stock_status ) {
            addButton.disabled = true;
        }

        addButton.addEventListener( 'click', () => {
            let variationId = 0;

            if ( hasVariations ) {
                variationId = parseInt( select.value || '0', 10 );
                if ( ! variationId ) {
                    showFeedback( i18n.select_variation || 'Please choose an option first.', true );
                    return;
                }
            }

            addItemToState( product.id, variationId );
            showFeedback( '', false );
        } );

        actions.appendChild( addButton );

        body.appendChild( title );
        body.appendChild( price );
        body.appendChild( actions );

        card.appendChild( image );
        card.appendChild( body );

        return card;
    }

    function addItemToState( productId, variationId ) {
        const product = productLookup.get( String( productId ) );
        if ( ! product ) {
            return;
        }

        let unitPrice = product.price || 0;
        let optionLabel = '';

        if ( variationId ) {
            const variations = variationLookup.get( String( productId ) );
            const variation = variations ? variations.get( String( variationId ) ) : null;
            if ( ! variation ) {
                showFeedback( i18n.error_generic || 'Variation unavailable.', true );
                return;
            }

            if ( ! variation.purchasable || 'outofstock' === variation.stock_status ) {
                showFeedback( i18n.error_generic || 'Variation unavailable.', true );
                return;
            }

            unitPrice = variation.price || unitPrice;
            optionLabel = variation.name || '';
        }

        const key = getItemKey( productId, variationId );
        const existing = state.items.get( key ) || {
            productId,
            variationId,
            name: product.name,
            optionLabel,
            quantity: 0,
            price: unitPrice,
            image: product.image,
        };

        existing.quantity += 1;
        state.items.set( key, existing );

        updateSummary();
    }

    function modifyItemQuantity( key, delta ) {
        const entry = state.items.get( key );
        if ( ! entry ) {
            return;
        }

        entry.quantity += delta;

        if ( entry.quantity <= 0 ) {
            state.items.delete( key );
        }

        updateSummary();
    }

    function removeItem( key ) {
        state.items.delete( key );
        updateSummary();
    }

    function calculateTotals() {
        let itemsCount = 0;
        let itemsTotal = 0;

        state.items.forEach( ( entry ) => {
            itemsCount += entry.quantity;
            itemsTotal += entry.quantity * entry.price;
        } );

        let boxTotal = 0;
        if ( state.box ) {
            boxTotal = state.box.price * state.box.quantity;
        }

        const grandTotal = itemsTotal + boxTotal;

        return {
            itemsCount,
            itemsTotal,
            boxTotal,
            grandTotal,
        };
    }

    function rebuildSelectedList() {
        if ( ! elements.selectedList ) {
            return;
        }

        clearElement( elements.selectedList );

        if ( state.box ) {
            const li = document.createElement( 'li' );
            li.className = 'create-box__selected-item create-box__selected-item--box';

            const info = document.createElement( 'div' );
            info.className = 'create-box__selected-info';

            const name = document.createElement( 'strong' );
            name.textContent = state.box.name;

            info.appendChild( name );

            const actions = document.createElement( 'div' );
            actions.className = 'create-box__selected-actions';

            const removeBox = document.createElement( 'button' );
            removeBox.type = 'button';
            removeBox.className = 'create-box__selected-remove';
            removeBox.dataset.removeBox = '1';
            removeBox.setAttribute( 'aria-label', i18n.remove || 'Remove' );
            removeBox.textContent = '×';

            actions.appendChild( removeBox );

            li.appendChild( info );
            li.appendChild( actions );

            elements.selectedList.appendChild( li );
        }

        state.items.forEach( ( entry, key ) => {
            const li = document.createElement( 'li' );
            li.className = 'create-box__selected-item';
            li.dataset.key = key;

            const info = document.createElement( 'div' );
            info.className = 'create-box__selected-info';

            const name = document.createElement( 'strong' );
            name.textContent = entry.name;
            info.appendChild( name );

            if ( entry.optionLabel ) {
                const variant = document.createElement( 'span' );
                variant.className = 'create-box__selected-meta';
                variant.textContent = entry.optionLabel;
                info.appendChild( variant );
            }

            const actions = document.createElement( 'div' );
            actions.className = 'create-box__selected-actions';

            const decrease = document.createElement( 'button' );
            decrease.type = 'button';
            decrease.className = 'create-box__selected-control';
            decrease.dataset.action = 'decrease';
            decrease.textContent = '–';

            const qty = document.createElement( 'span' );
            qty.className = 'create-box__selected-qty';
            qty.textContent = entry.quantity.toString();

            const increase = document.createElement( 'button' );
            increase.type = 'button';
            increase.className = 'create-box__selected-control';
            increase.dataset.action = 'increase';
            increase.textContent = '+';

            const remove = document.createElement( 'button' );
            remove.type = 'button';
            remove.className = 'create-box__selected-remove';
            remove.dataset.action = 'remove';
            remove.textContent = '×';

            const quantityGroup = document.createElement( 'div' );
            quantityGroup.className = 'create-box__selected-quantity';
            quantityGroup.appendChild( decrease );
            quantityGroup.appendChild( qty );
            quantityGroup.appendChild( increase );

            actions.appendChild( quantityGroup );
            actions.appendChild( remove );

            li.appendChild( info );
            li.appendChild( actions );

            elements.selectedList.appendChild( li );
        } );
    }

    function updateSummary() {
        const totals = calculateTotals();

        if ( elements.summaryGrand ) {
            elements.summaryGrand.textContent = formatMoney( totals.grandTotal );
        }

        if ( elements.submitTotal ) {
            elements.submitTotal.textContent = formatMoney( totals.grandTotal );
        }

        const itemsNeeded = Math.max( 0, ( rules.min_items || 0 ) - totals.itemsCount );

        if ( elements.summaryItems ) {
            if ( itemsNeeded > 0 ) {
                elements.summaryItems.textContent = ( i18n.items_needed || 'Your bundle needs at least %d more item(s).' ).replace( '%d', itemsNeeded );
                elements.summaryItems.hidden = false;
            } else {
                elements.summaryItems.hidden = true;
            }
        }

        if ( elements.summaryBox ) {
            if ( rules.require_box && ! state.box ) {
                elements.summaryBox.textContent = i18n.box_required || 'Add a required single product from the box collection to proceed.';
                elements.summaryBox.hidden = false;
            } else {
                elements.summaryBox.hidden = true;
            }
        }

        if ( elements.summaryTotal ) {
            if ( rules.min_total && totals.grandTotal < rules.min_total ) {
                const requiredText = i18n.total_required || 'Your total amount needs to be at least %s to proceed.';
                elements.summaryTotal.textContent = requiredText.replace( '%s', formatMoney( rules.min_total ) );
                elements.summaryTotal.hidden = false;
            } else {
                elements.summaryTotal.hidden = true;
            }
        }

        rebuildSelectedList();
        updateSubmitState( totals, itemsNeeded );
    }

    function updateSubmitState( totals, itemsNeeded ) {
        const ready = validateReady( totals, itemsNeeded );
        if ( elements.submitButton ) {
            elements.submitButton.disabled = ! ready || state.busy;
        }

        if ( elements.submitLabel ) {
            const label = state.busy ? ( i18n.button_pending || 'Adding…' ) : ( i18n.button_label || 'Add to Cart' );
            elements.submitLabel.textContent = label;
        }
    }

    function validateReady( totals, itemsNeeded ) {
        if ( totals === undefined ) {
            totals = calculateTotals();
        }

        if ( itemsNeeded === undefined ) {
            itemsNeeded = Math.max( 0, ( rules.min_items || 0 ) - totals.itemsCount );
        }

        if ( itemsNeeded > 0 ) {
            return false;
        }

        if ( rules.require_box && ! state.box ) {
            return false;
        }

        if ( rules.min_total && totals.grandTotal < rules.min_total ) {
            return false;
        }

        if ( totals.itemsCount === 0 ) {
            return false;
        }

        return true;
    }

    function handleSelectedListClick( event ) {
        const action = event.target.dataset.action;
        const removeBox = event.target.matches( '[data-remove-box]' );

        if ( removeBox ) {
            state.box = null;
            const cards = elements.boxGrid ? elements.boxGrid.querySelectorAll( '.create-box-card' ) : [];
            cards.forEach( ( card ) => {
                card.classList.remove( 'is-selected' );
                const button = card.querySelector( '[data-box-select]' );
                if ( button ) {
                    button.textContent = i18n.select_box || 'Select Box';
                }
            } );
            updateSummary();
            return;
        }

        if ( ! action ) {
            return;
        }

        const li = event.target.closest( '.create-box__selected-item' );
        if ( ! li ) {
            return;
        }

        const key = li.dataset.key;
        if ( ! key ) {
            return;
        }

        if ( 'increase' === action ) {
            modifyItemQuantity( key, 1 );
        } else if ( 'decrease' === action ) {
            modifyItemQuantity( key, -1 );
        } else if ( 'remove' === action ) {
            removeItem( key );
        }
    }

    async function submitBundle() {
        if ( state.busy ) {
            return;
        }

        const totals = calculateTotals();
        const itemsNeeded = Math.max( 0, ( rules.min_items || 0 ) - totals.itemsCount );

        if ( ! validateReady( totals, itemsNeeded ) ) {
            showFeedback( i18n.error_generic || 'Please complete the required selections first.', true );
            return;
        }

        const payloadToSend = {
            box: state.box ? {
                product_id: state.box.productId,
                variation_id: state.box.variationId || 0,
                quantity: state.box.quantity || 1,
            } : null,
            items: [],
        };

        state.items.forEach( ( entry ) => {
            payloadToSend.items.push( {
                product_id: entry.productId,
                variation_id: entry.variationId || 0,
                quantity: entry.quantity,
            } );
        } );

        if ( ! payloadToSend.items.length ) {
            showFeedback( i18n.error_generic || 'Please add at least one item.', true );
            return;
        }

        state.busy = true;
        updateSubmitState( totals, itemsNeeded );
        showFeedback( '', false );

        try {
            const response = await fetch( `${ appData.restBase }/add`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': appData.nonce || '',
                },
                body: JSON.stringify( payloadToSend ),
                credentials: 'same-origin',
            } );

            const result = await response.json();

            if ( ! response.ok || ! result || result.error ) {
                const message = result && result.message ? result.message : ( i18n.error_generic || 'Something went wrong. Please try again.' );
                throw new Error( message );
            }

            showFeedback( i18n.added || 'Bundle added! Redirecting…', false );

            if ( result.redirect ) {
                setTimeout( () => {
                    window.location.href = result.redirect;
                }, 600 );
            }
        } catch ( error ) {
            showFeedback( error.message || ( i18n.error_generic || 'Something went wrong. Please try again.' ), true );
        } finally {
            state.busy = false;
            updateSubmitState();
        }
    }

    function init() {
        toggleHidden( elements.builder, false );

        if ( elements.subtitle ) {
            setHtml( elements.subtitle, payload.intro || '' );
        }

        renderBoxes();
        renderSections();
        updateSummary();

        if ( elements.selectedList ) {
            elements.selectedList.addEventListener( 'click', handleSelectedListClick );
        }

        if ( elements.submitButton ) {
            elements.submitButton.addEventListener( 'click', submitBundle );
        }
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
} )();
