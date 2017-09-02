var betterWorld = betterWorld || {};
betterWorld.quotes = betterWorld.quotes || {};
betterWorld.quotes.handleWidgetUpdated = function( widgetId ) {
    jQuery( '#' + widgetId + ' .betterworld_tagsSelector' ).suggest( ajaxurl + '?action=ajax-tag-search&tax=quote-taxonomy', {
        multiple: true,
        multipleSep: ','
    });
};

betterWorld.quotes.handleWidgetAdded = betterWorld.quotes.handleWidgetUpdated;

jQuery( document ).on( 'widget-updated', function( event, widget ) {
    var widgetId = jQuery( widget ).attr( 'id' );

    // any code that needs to be run when a widget gets updated goes here
    // widgetId holds the ID of the actual widget that got updated
    // be sure to only run the code if one of your widgets got updated
    // otherwise the code will be run when any widget is updated
    betterWorld.quotes.handleWidgetUpdated( widgetId );
});

jQuery( document ).on( 'widget-added', function( event, widget ) {
    var widgetId = jQuery( widget ).attr( 'id' );

    // any code that needs to be run when a new widget gets added goes here
    // widgetId holds the ID of the actual widget that got added
    // be sure to only run the code if one of your widgets got added
    // otherwise the code will be run when any widget is added
    betterWorld.quotes.handleWidgetAdded( widgetId );
});
