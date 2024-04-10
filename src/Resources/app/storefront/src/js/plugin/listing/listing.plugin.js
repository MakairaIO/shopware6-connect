import deepmerge from "deepmerge";
import Debouncer from "src/helper/debouncer.helper";
import ElementReplaceHelper from "src/helper/element-replace.helper";
import PluginManager from "src/plugin-system/plugin.manager";
import ShopwareListingPlugin from "src/plugin/listing/listing.plugin";

let ListingPlugin = ShopwareListingPlugin;
try {
    ListingPlugin = PluginManager.getPlugin("Listing").get("class");
} catch (e) {}


/**
 * MakairaListingPlugin
 * - On any response, replace the filters and reinit the registry
 */
export default class MakairaListingPlugin extends ListingPlugin {
    static options = deepmerge(ListingPlugin.options, {
        filterPanelReplaceSelectors: [
            ".js-makaira-filter-panel-sorting",
            ".js-makaira-filter-panel-items",
            ".js-makaira-filter-panel-active",
        ],
    });

    _init() {
        super._init();

        this.$emitter.subscribe("Listing/afterRenderResponse", this.reinitFilterPanel.bind(this));
    }
    /**
     * Makaira rerenders the available filter after any change, so we need to
     * reinit the markup & registry
     * @param {CustomEvent} event
     */
    reinitFilterPanel(event) {
        const response = event.detail.response;

        /**
         * @note ListingPaginationPlugin is already replaced by `ShopwareListingPlugin::renderResponse`
         */
        ElementReplaceHelper.replaceFromMarkup(
            response,
            this.options.filterPanelReplaceSelectors,
            false
        );

        this.refreshRegistry();
    }
}
