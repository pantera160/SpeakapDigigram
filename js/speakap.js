/*!
 * Speakap API integration for 3rd party apps, version 1.0
 * http://www.speakap.nl/
 *
 * Copyright (C) 2013-2014 Speakap BV
 */
(function(factory) {

    "use strict";

    if (typeof exports === "object" && exports &&
        typeof module === "object" && module && module.exports === exports) {
        // Node.js (Browserify)
        module.exports = factory(require("jquery"));
    } else if (typeof define === "function" && define.amd) {
        // AMD. Register as anonymous module.
        define(["jquery"], factory);
    } else {
        // Browser globals.
        /* global jQuery */
        window.Speakap = factory(jQuery);
    }
}(function($, undefined) {

    "use strict";

    /**
     * The global Speakap object.
     *
     * This object is either imported through the AMD module loader or is accessible as a global
     * Speakap variable.
     *
     * Before you load this library, you should set the App ID and the signed request that was
     * received by the application on the global Speakap object.
     *
     * Example:
     *
     *   <script type="text/javascript">
     *       var Speakap = { appId: "YOUR_APP_ID", signedRequest: "SIGNED_REQUEST_PARAMS" };
     *   </script>
     *   <script type="text/javascript" src="js/jquery.min.js"></script>
     *   <script type="text/javascript" src="js/speakap.js"></script>
     */
    var Speakap = function() {

        /**
         * App data used for opening the lightbox.
         */
        this.appData = window.Speakap.appData || "";

        /**
         * The application's app ID.
         */
        this.appId = window.Speakap.appId || "APP ID IS MISSING";

        /**
         * Promise that will be fulfilled when the handshake has completed. You can use this to
         * make sure you don't run any code before the handshake has completed.
         *
         * Example:
         *
         *   Speakap.doHandshake.then(function() {
         *       // make calls to the Speakap API proxy...
         *   })
         */
        this.doHandshake = null;

        /**
         * The signed request posted to the application.
         */
        this.signedRequest = window.Speakap.signedRequest || "";

        /**
         * Token to use to identify the consumer with the API proxy.
         *
         * Will be set by the handshake procedure. Be sure not to call any other methods before the
         * handshake has completed by using the doHandshake promise.
         *
         * If this file is loaded in the context of a lightbox, the token is injected automatically.
         */
        this.token = window.Speakap.token || "";

        window.addEventListener("message", $.proxy(this._handleMessage, this));

        this._callId = 0;
        this._calls = {};

        this._listeners = {};

        if (this.signedRequest || this.token) {
            this._doHandshake();
        }
    };

    /**
     * Sends a remote request to the API.
     *
     * This method can be used as a replacement for $.ajax(), but with a few differences:
     * - All requests are automatically signed using the access token of the host application,
     *   but the app's permissions may be limited based on the App ID.
     * - Error handlers will receive an error object (with code and message properties) as their
     *   first argument, instead of a jqXHR object.
     * - The URL property is interpreted as a path under the Speakap API, limited in scope to
     *   the current network. Eg. use "/users/" to request
     *                                    "https://api.speakap.nl/networks/:networkEID/users/".
     * - The only supported HTTP method is GET.
     */
    Speakap.prototype.ajax = function(url, settings) {

        if (settings) {
            settings.url = url;
        } else if (typeof url === "string") {
            settings = { url: url };
        } else {
            settings = url;
        }

        settings.type = "GET";

        var context = settings.context;
        delete settings.context;

        var successCallback = settings.success;
        delete settings.success;

        var errorCallback = settings.error;
        delete settings.error;

        var promise = this._call("ajax", settings, { context: context, expectResult: true });

        if (successCallback) {
            promise.done(function() {
                successCallback.apply(context, arguments);
            });
        }
        if (errorCallback) {
            promise.fail(function() {
                errorCallback.apply(context, arguments);
            });
        }

        return promise;
    };

    /**
     * Presents a confirmation lightbox to the user.
     *
     * This method provides a convenient way to present a confirmation lightbox, similar to
     * JavaScript's native confirm() method, with an API that is much easier than creating a custom
     * lightbox.
     *
     * @param options Required options object. May contain the following properties:
     *                cancelLabel - The label to display on the cancel button (default: localized
     *                              "Cancel").
     *                confirmLabel - The label to display on the confirm button (default: localized
     *                               "OK").
     *                context - Context in which to execute the promise callbacks.
     *                text - The text to display in the confirmation dialog. Only plain text is
     *                       supported, with the exception of <b> and <i> tags and newlines.
     *                title - The title to display on the confirmation dialog.
     *
     * @return jQuery Deferred promise that gets fulfilled when the lightbox is confirmed, or failed
     *         when the lightbox is cancelled.
     */
    Speakap.prototype.confirm = function(options) {

        var data = {};
        for (var key in options) {
            if (options.hasOwnProperty(key) && key !== "context") {
                var value = options[key];
                data[key] = value.toString();
            }
        }

        return this._call("confirm", data, {
            context: options.context,
            expectResult: true
        });
    };

    /**
     * Retrieves the currently logged in user.
     *
     * This method returns a $.Deferred object that is resolved with the user object as first
     * argument when successful.
     *
     * The returned user object only contains the EID, name, fullName and avatarThumbnailUrl
     * properties.
     *
     * @param options Optional options object. May contain a context property containing the context
     *                in which the deferred listeners will be executed.
     */
    Speakap.prototype.getLoggedInUser = function(options) {

        options = options || {};

        return this._call("getLoggedInUser", null, {
            context: options.context,
            expectResult: true
        });
    };

    /**
     * Stops listening to an event emitted by the Speakap host application.
     *
     * @param event Event that was being listened to.
     * @param callback Callback function that was executed when the event was emitted.
     * @param context Optional context in which the listener was executed.
     */
    Speakap.prototype.off = function(event, callback, context) {

        var listeners = this._listeners[event] || [];
        for (var i = 0; i < listeners.length; i++) {
            var listener = listeners[i];
            if (listener.callback === callback && listener.context === context) {
                listeners.splice(i, 1);
                i--;
            }
        }
        this._listeners[event] = listeners;
    };

    /**
     * Starts listening to an event emitted by the Speakap host application.
     *
     * @param event Event to listen to.
     * @param listener Callback function to execute when the event is emitted.
     * @param context Optional context in which to execute the listener.
     *
     * The following events are currently supported:
     *
     * "resolveLightbox" - Emitted in a lightbox when the lightbox is resolved. The lightbox is
     *                     expected to reply to this event with a result object containing two
     *                     properties: status and data. Status should be "success" or else the
     *                     resolve action is considered to have failed, and the data should be the
     *                     data to be passed back to the caller that opened the lightbox.
     * "saveSettings" - Emitted in the "install-wizard" position when the user has requested to
     *                  save the settings. For more information, see:
     *                      http://developers.speakap.io/portal/tutorials/installation_wizard.html
     *
     * @see replyEvent()
     */
    Speakap.prototype.on = function(event, callback, context) {

        var listeners = this._listeners[event] || [];
        listeners.push({ callback: callback, context: context });
        this._listeners[event] = listeners;
    };

    /**
     * Opens the application in Speakap, optionally passing in appData.
     *
     * This only works if the application has defined an entry for the "main" position and is
     * intended for widget positions to link to the main position.
     *
     * @param appData Optional appData to pass. This data can be read by the opened application.
     */
    Speakap.prototype.openAppLink = function(appData) {

        return this._call("openAppLink", { appData: appData ? appData.toString() : '' });
    };

    /**
     * Presents a lightbox to the user.
     *
     * The lightbox contains an iframe of which the content is provided by your application and
     * there are three different ways of loading this content, each with their respective advantages
     * and disadvantages. Depending on your requirements and use case, you should pick one of these:
     *
     * 1) The lightbox content is loaded remotely from a predefined position defined in the
     *    application manifest. This method gives you the most freedom to do with the lightbox what
     *    you want, but its downsides are that you as application developer need to provide a remote
     *    endpoint that will load the lightbox contents and the extra remote requests will cause a
     *    delay between the moment the lightbox opens and the display of its contents.
     *
     * 2) The lightbox content is provided statically through the content parameter. Because the
     *    content is available right away, there is no delay between the opening of the lightbox and
     *    the display of the contents. The downside is that by default, no scripts are allowed to be
     *    executed inside the iframe, thus limiting yourself to mostly static content.
     *
     * 3) The final method is an extension of the second, but in this case scripts are allowed to be
     *    executed as well. Unfortunately, for security reasons, this method is currently *not*
     *    compatible with Internet Explorer.
     *
     * Among the following options, "buttonPositioning", "buttons", "context", "height", "title"
     * and "width" are supported regardless of how the content is loaded. The "position" and
     * "appData" options are exclusive to the first mechanism of content loading, and the "content",
     * "css" and "includeSpeakapCSS" options are used by the other two mechanisms. The options
     * "js" and "hasScripts" are exclusive to the third mechanism.
     *
     * It is possible to supply options for both the first and the third mechanism in a single call
     * to openLightbox(). In this case, the third mechanism is used if the user's browser supports
     * it, and the first mechanism is used as fallback.
     *
     * @param options Required options object. May contain the following properties:
     *                appData - App data that should be POSTed with the signed request when loading
     *                          the lightbox content from a manifest position. If the third
     *                          mechanism for loading the content is used, the appData is made
     *                          available as property under the Speakap object.
     *                buttonPositioning - String "top" or "bottom", depending on whether the given
     *                                    buttons should be displayed in the header or the footer.
     *                                    Default is "top".
     *                buttons - Array of button objects for the buttons to show below the lightbox.
     *                          Each button object may have the following properties:
     *                          enabled - Boolean indicating whether the button is enabled. Default
     *                                    is true.
     *                          label - String label of the button.
     *                          positioning - String "left" or "right", depending on which side the
     *                                        button should be displayed. Default is "right".
     *                          primary - Boolean indicating whether the button is the primary
     *                                    button. The primary button is styled in the call-to-action
     *                                    color (typically green) and is selected when the user
     *                                    presses Ctrl+Enter.
     *                          type - String type of the button, used for identifying the button.
     *                                 There are two types supported:
     *                                 "close" - Closes the lightbox when clicked.
     *                                 "resolve" - Resolves the lightbox when clicked.
     *                content - HTML content to display in the body of the iframe. If this option
     *                          is provided, the second or third mechanism for loading the content
     *                          is used, depending on the value of the hasScripts option.
     *                context - Context in which to execute the promise callbacks.
     *                css - Array of URLs to CSS resources that should be included by the iframe.
     *                hasScripts - If the content option is provided, this option has to be set to
     *                             true to enable the execution of scripts in the lightbox. It is
     *                             implicitly set to true if any JavaScript URLs are specified, but
     *                             has to be explicitly set to true if you specify inline event
     *                             handlers in your HTML content, for example. An important caveat
     *                             is that when this property is true (whether implicit or
     *                             explicit) the lightbox will *not* work in Internet Explorer,
     *                             unless you also a provide a position as fallback.
     *                height - Lightbox height in pixels. The minimum permitted height is 100
     *                         pixels, and the maximum permitted height is 540 pixels.
     *                includeSpeakapCSS - Boolean whether Speakap's "base.css" and "branding.css"
     *                                    should be included. Default is true.
     *                js - Array of URLs to JavaScript resources that should be loaded by the
     *                     iframe. Note that you need to include your own reference to this
     *                     speakap.js file if you want to be able to use this API from the iframe.
     *                position - Position from the application manifest to load in the iframe. If
     *                           this option is provided, the first mechanism for loading the
     *                           content is used. For the purpose of validation, the name of the
     *                           position should end with "-lightbox".
     *                title - Title of the lightbox.
     *                width - Lightbox width in pixels. The minimum permitted width is 100
     *                        pixels, and the maximum permitted width is 740 pixels.
     *
     * @return jQuery Deferred promise that gets fulfilled when the lightbox is resolved, or failed
     *         when the lightbox is closed otherwise.
     *
     * When the lightbox is resolved, the promise callback receives a data parameter. If you have
     * used the second method of content loading, this data parameter is populated automatically
     * to contain key-value pairs for all the input elements inside the lightbox, where the key is
     * the name of the input element and the value is the value of the input. If you have used the
     * first or third method of content loading, this data parameter should be populated by the
     * scripts upon receiving the "resolveLightbox" event.
     *
     * All URLs given for CSS and JavaScript resources have to be absolute URLs.
     *
     * Within the iframe, this speakap.js file is available if you include it with the JavaScript
     * resources, giving you access to the global Speakap object from the iframe (including event
     * handlers defined on the lightbox). Specifically, the following methods can be used from a
     * lightbox context:
     * - getLoggedInUser()
     * - setButtonEnabled()
     * - showError()
     * - showNotice()
     */
    Speakap.prototype.openLightbox = function(options) {

        var data = {};
        for (var key in options) {
            if (options.hasOwnProperty(key) && key !== "context") {
                var value;
                if (key === "events") {
                    value = {};
                    var events = options[key];
                    for (var eventName in events) {
                        if (events.hasOwnProperty(eventName)) {
                            value[eventName] = events[eventName].toString();
                        }
                    }
                } else {
                    value = options[key];

                    if (key === "buttons") {
                        for (var i = 0; i < value.length; i++) {
                            var button = value[i];
                            if (button.label) {
                                button.label = button.label.toString();
                            } else {
                                throw new Error("Buttons should have a label");
                            }
                            if (button.type !== "resolve" && button.type !== "close") {
                                throw new Error("Button type must be 'resolve' or 'close'");
                            }
                        }
                    } else if (key === "title") {
                        value = value.toString();
                    }
                }

                data[key] = value;
            }
        }

        if (data.js) {
            data.hasScripts = true;
        }

        if (data.content) {

        } else if (data.position) {
            if (data.hasScripts) {
                throw new Error("The hasScripts parameter can only be used with static content");
            }
        } else {
            throw new Error("No content has been specified to load in the lightbox");
        }

        return this._call("openLightbox", data, {
            context: options.context,
            expectResult: true
        });
    };

    /**
     * Opens a URL in a new window/tab. Subsequent calls to this method will open the method in the
     * same window or tab.
     *
     * Because of sandbox restrictions, applications are not allowed to open links. This method
     * still allows opening of links, if only in a new window.
     *
     * @param url The URL to open. Must be an absolute URL.
     */
    Speakap.prototype.openUrl = function(url) {

        if (typeof url !== 'string') {
            throw new Error('URL must be a string');
        }

        return this._call("openUrl", { url: url });
    };

    /**
     * Sends a reply to an event generated by the Speakap host application.
     *
     * @param event Event to reply to.
     * @param data Data to send back to the host application.
     */
    Speakap.prototype.replyToEvent = Speakap.prototype.replyEvent = function(event, data) {

        if (event.eventId) {
            this._call("replyEvent", $.extend(data, { eventId: event.eventId }));
        } else {
            console.log("The host did not expect a reply to this event");
        }
    };

    /**
     * Opens a lightbox for selecting network members.
     *
     * @param options Optional options object. May contain the following properties:
     *                context - Context in which to execute the promise callbacks.
     *                description - Description text informing the user what to do.
     *                excludedMemberIds - Array of EIDs of members which may not be selected.
     *                                    By default, only the logged in user may not be selected.
     *                selectedMemberIds - Optional array of pre-selected member EIDs.
     *                selectMultiple - Boolean determining whether multiple members may be
     *                                 selected (default: true).
     *                submitButtonLabel - Label of the select button.
     *                title - Lightbox title.
     *
     * @return jQuery Deferred promise that gets fulfilled when one or more members have been
     *         selected, or failed when the action is canceled.
     *
     * When one or more members have been selected, the promise callback receives a data parameter
     * containing a single property, depending on the value of the selectMultiple option:
     *     memberId - EID of the selected member.
     *     memberIds - Array of EIDs of the selected members.
     *
     * Note: In some situations when you use the selectedMemberIds option, there may be a short
     *       delay between calling the method and the moment the lightbox opens, so it is advised to
     *       display some loading indicator when you use this option.
     */
    Speakap.prototype.selectMembers = function(options) {

        options = options || {};

        return this._call("selectMembers", {
            description: (options.description ? "" + options.description : ""),
            excludedUserIds: options.excludedMemberIds,
            selectedMemberIds: options.selectedMemberIds,
            selectMultiple: (options.selectMultiple !== false),
            submitButtonLabel: (options.submitButtonLabel ? "" + options.submitButtonLabel : ""),
            title: (options.title ? "" + options.title : "")
        }, {
            context: options.context,
            expectResult: true
        });
    };

    /**
     * Opens a lightbox for selecting a network role.
     *
     * @param options Optional options object. May contain the following properties:
     *                context - Context in which to execute the promise callbacks.
     *                description - Description text informing the user what to do.
     *
     * @return jQuery Deferred promise that gets fulfilled when a role is selected, or failed when
     *         the action is canceled.
     *
     * When a role is selected, the promise callback receives a data parameter containing two
     * properties:
     *     key - Key of the selected role. This can be an EID of a custom role or a string
     *           identifier of a predefined role.
     *     name - Name of the role. This is the name of the role as given by the administrator, or
     *            the localized name of a predefined role. Because the name is locale-dependent, it
     *            is only intended for informing the user, and should not be stored for any other
     *            purposes.
     */
    Speakap.prototype.selectRole = function(options) {

        options = options || {};

        return this._call("selectRole", {
            description: options.description
        }, {
            context: options.context,
            expectResult: true
        });
    };

    /**
     * Toggles the enabled state of one of the lightbox's buttons.
     *
     * @param type Type of the button to enable or disable.
     * @param enabled True if the button should be enabled, false if it should be disabled.
     *
     * This method is only available from the content of a lightbox iframe.
     */
    Speakap.prototype.setButtonEnabled = function(type, enabled) {

        return this._call("setButtonEnabled", { type: type, enabled: enabled });
    };

    /**
     * Shows an error message to the user.
     *
     * @param message Localized message to show the user.
     */
    Speakap.prototype.showError = function(message) {

        return this._call("showError", { message: message });
    };

    /**
     * Shows a notice or confirmation message to the user.
     *
     * @param message Localized message to show the user.
     */
    Speakap.prototype.showNotice = function(message) {

        return this._call("showNotice", { message: message });
    };

    // PRIVATE methods

    Speakap.prototype._call = function(method, data, options) {

        options = options || {};

        var deferred = new $.Deferred();

        var cid;
        if (options.expectResult) {
            cid = "c" + this._callId++;
            this._calls[cid] = {
                context: options.context,
                deferred: deferred
            };
        } else {
            deferred.resolveWith(options.context);
        }

        window.parent.postMessage({
            appId: this.appId,
            callId: cid,
            method: method,
            settings: data || {},
            token: this.token
        }, "*");

        return deferred.promise();
    };

    Speakap.prototype._doHandshake = function() {

        if (this.token) {
            this.doHandshake = this._call("handshake", { token: this.token }, {
                expectResult: true
            });
        } else {
            this.doHandshake = this._call("handshake", { signedRequest: this.signedRequest }, {
                context: this,
                expectResult: true
            }).then(function(result) {
                this.token = result.token;
            });
        }
    };

    Speakap.prototype._handleMessage = function(event) {

        var data = event.data || {};

        if (data.event) {
            var listeners = this._listeners[data.event] || [];
            for (var i = 0; i < listeners.length; i++) {
                var listener = listeners[i];
                listener.callback.call(listener.context, data.data);
            }
        } else {
            var calls = this._calls;
            if (calls.hasOwnProperty(data.callId)) {
                var callback = calls[data.callId];
                delete calls[data.callId];

                var deferred = callback.deferred;
                if (data.error && data.error.code === 0) {
                    deferred.resolveWith(callback.context, [data.result]);
                } else {
                    deferred.rejectWith(callback.context, [data.error]);
                }
            }
        }
    };

    return new Speakap();

}));
