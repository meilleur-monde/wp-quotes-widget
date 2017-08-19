var betterWorld = betterWorld || {};
betterWorld.quotes = betterWorld.quotes || {};
betterWorld.quotes.refresh = function (args) {
    if (args.ajaxRefresh && !args.autoRefresh) {
        jQuery("#" + args.instanceID + " .nav-next").html(quotescollectionAjax.loading);
        jQuery.ajax({
            type: "POST",
            url: quotescollectionAjax.ajaxUrl,
            data: "action=quotescollection&_ajax_nonce=" + quotescollectionAjax.nonce + "&current=" + args.currQuoteID + "&char_limit=" + args.charLimit + "&tags=" + args.tags + "&orderby=" + args.orderBy,
            success: function (response) {
                if (response == '-1' || !response) {
                    if (args.ajaxRefresh && args.autoRefresh)
                        quotescollectionTimer(args);
                    else if (args.ajaxRefresh && !args.autoRefresh)
                        jQuery("#" + args.instanceID + " .nav-next").html('<a class=\"next-quote-link\" style=\"cursor:pointer;\" onclick=\"quotescollectionRefreshInstance(\'' + args.instanceID + '\')\">' + quotescollectionAjax.nextQuote + '</a>');
                }
                else {
                    if (args.dynamicFetch) {
                        args.dynamicFetch = 0;
                    }
                    args.currQuoteID = response.quote_id;
                    quotescollectionInstances[args.instanceID] = args;
                    display = quotescollectionDisplayFormat(response, args);
                    jQuery("#" + args.instanceID).hide();
                    jQuery("#" + args.instanceID).html(display, args);
                    jQuery("#" + args.instanceID).fadeIn('slow');
                    if (args.ajaxRefresh && args.autoRefresh)
                        quotescollectionTimer(args);
                }
            },
            error: function (xhr, textStatus, errorThrown) {
                console.log(textStatus + ' ' + xhr.status + ': ' + errorThrown);
                if (args.ajaxRefresh && !args.autoRefresh) {
                    jQuery("#" + args.instanceID + " .nav-next").html('<a class=\"next-quote-link\" style=\"cursor:pointer;\" onclick=\"quotescollectionRefreshInstance(\'' + args.instanceID + '\')\">' + quotescollectionAjax.nextQuote + ';</a>');
                }
            }
        });

    }
};
