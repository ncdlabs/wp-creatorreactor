/**
 * FanBridge Users Tab Script
 */

(function($) {
    // Configuration - user may need to adjust these selectors
    var tabNavSelector = '.nav-tab-wrapper'; // container for the tabs
    var tabContentSelector = '.tab-content'; // container for the tab panes
    var pluginSettingsPageSelector = '.wrap:has(h2:contains("FanBridge"))'; // adjust to match the plugin settings page

    // Wait for DOM ready
    $(function() {
        // Check if we are on the plugin settings page
        if ( $(pluginSettingsPageSelector).length === 0 ) {
            return;
        }

        // Add our tab
        var $tabNav = $(tabNavSelector);
        if ( $tabNav.length === 0 ) {
            console.warn('FanBridge Users Tab: Could not find tab navigation container. Check tabNavSelector.');
            return;
        }

        // Add our tab
        var $newTab = $('<a>', {
            href: '#',
            'class': 'nav-tab',
            text: 'Users',
            id: 'fb-users-tab'
        });
        $tabNav.append($newTab);

        // Add our tab panel
        var $tabContent = $(tabContentSelector);
        if ( $tabContent.length === 0 ) {
            console.warn('FanBridge Users Tab: Could not find tab content container. Check tabContentSelector.');
            return;
        }

        var $newPanel = $('<div>', {
            'class': 'tab-pane',
            id: 'fb-users-panel',
            html: '<p>Loading...</p>'
        });
        $tabContent.append($newPanel);

        // Hide all tabs and panels initially? We'll handle via click.
        // We'll set up click event for our tab.
        $newTab.on('click', function(e) {
            e.preventDefault();

            // Activate our tab
            $tabNav.find('.nav-tab').removeClass('nav-tab-active');
            $newTab.addClass('nav-tab-active');

            // Show our panel and hide others
            $tabContent.find('.tab-pane').hide();
            $newPanel.show();

            // If our panel is empty (only has "Loading..."), load the content via AJAX
            if ( $newPanel.find('p:contains("Loading...")').length ) {
                loadUserTable();
            }
        });

        // Function to load the user table via AJAX
        function loadUserTable() {
            $.post(
                ajaxurl, // WordPress AJAX URL
                {
                    action: 'fb_get_users_table',
                    security: fbUsersTable.nonce // we will localize this
                },
                function(response) {
                    if ( response.success ) {
                        $newPanel.html(response.data);
                    } else {
                        $newPanel.html('<p>Error loading user table: ' + response.data + '</p>');
                    }
                }
            ).fail(function() {
                $newPanel.html('<p>Error loading user table.</p>');
            });
        }
    });
})(jQuery);