<template>
    <div>
        <l-map :zoom="2" :center="center" :maxBoundsViscosity="1.0" :worldCopyJump="true" style="width: 100%; height: 100%;" @contextmenu="addMarker" ref="map" >
            <l-tile-layer :url="url" :attribution="attribution"></l-tile-layer>
            <l-locatecontrol />
            <v-geosearch :options="geosearchOptions"></v-geosearch>
            <l-layer-group ref="hello_popup">
                <l-popup>
                    <form v-if="canPost == 'yes'" method="POST" action="/incidents" @submit.prevent="submitForm()" :disabled="!submit_data.category_name">
                        <label class="my-1 mr-2">Marker label:</label>
                        <multiselect v-model="fullCategory" @input="handleSelectInput" track-by="name" label="name" placeholder="Your marker label" tag-placeholder="Add this as new label" :options="categories" :searchable="true" :allow-empty="false" :taggable="true" @tag="addTag" style="width:250px;" :show-labels="false" class="your_custom_class" :loading="submit_data.loading" :internal-search="false" :clear-on-select="false" :options-limit="300" :limit="3" :max-height="600" :show-no-results="false" @search-change="asyncFind" :preserve-search="true" required>
                            <template slot="limit" slot-scope="{ option }">Keep typing to refine your search</template>
                            <template slot="noOptions">Search for or add a new label</template>
                            <template slot="singleLabel" slot-scope="{ option }"><strong>{{ option.name }}</strong></template>
                            <template slot="option" slot-scope=" props "><img v-if="props.option.icon" class="rounded img-thumbnail mr-1" height="25" width="25" :src="props.option.icon" alt="" style="position: initial;">{{ props.option.name }}
                            </template>
                        </multiselect>
                        <input type="submit" value="Add marker" class="btn btn-primary btn-sm my-1" :disabled="submit_data.loading || !submit_data.category_name">
                    </form>
                    <div v-else-if="canPost == 'only_logged_in'">You must be logged in to Cartes.io to post on this map.</div>
                    <div v-else>You can't create markers on this map.</div>
                </l-popup>
            </l-layer-group>
            <l-marker-cluster>
                <l-marker v-for="incident in activeIncidents" :lat-lng="incident.location.coordinates" :key="incident.id+'marker'" @click="handleOpenedPopup($event, incident.id)">
                    <l-icon :icon-url="incident.category.icon" :icon-size="[30, 30]" :icon-anchor="[15, 15]" />
                    <l-popup @ready="openPopup">
                        <p class="mb-1" style="min-width: 200px;"><b>{{incident.category.name}}</b></p>
                        <p class="mt-0 mb-1" v-if="incident.marker">{{ incident.marker.label }}</p>
                        <small class="w-100 d-block">Last report: <span class='timestamp' :datetime="incident.updated_at">{{ incident.updated_at }}</span>.</small>
                        <a class="btn btn-link btn-sm text-danger" v-if="inLocalStorageKey(incident.id)" @click="deleteIncident(incident.id)" :disabled="submit_data.loading">Delete</a>
                    </l-popup>
                </l-marker>
            </l-marker-cluster>
        </l-map>
        <div class="alert bg-primary map-notification" role="alert" v-if="new_message">
            <h4 class="alert-heading">A new marker was created</h4>
            <p class="mb-0">{{new_message}}</p>
        </div>
    </div>
</template>
<script>
// URL MAP HASH FUNCTION
(function(window) {
    var HAS_HASHCHANGE = (function() { var doc_mode = window.documentMode; return ('onhashchange' in window) && (doc_mode === undefined || doc_mode > 7) })();
    L.Hash = function(map) { this.onHashChange = L.Util.bind(this.onHashChange, this); if (map) { this.init(map) } };
    L.Hash.parseHash = function(hash) {
        if (hash.indexOf('#') === 0) { hash = hash.substr(1) }
        var args = hash.split("/");
        if (args.length == 3) {
            var zoom = parseInt(args[0], 10),
                lat = parseFloat(args[1]),
                lon = parseFloat(args[2]);
            if (isNaN(zoom) || isNaN(lat) || isNaN(lon)) { return !1 } else { return { center: new L.LatLng(lat, lon), zoom: zoom } }
        } else { return !1 }
    };
    L.Hash.formatHash = function(map) {
        var center = map.getCenter(),
            zoom = map.getZoom(),
            precision = Math.max(0, Math.ceil(Math.log(zoom) / Math.LN2));
        return "#" + [zoom, center.lat.toFixed(precision), center.lng.toFixed(precision)].join("/")
    }, L.Hash.prototype = {
        map: null,
        lastHash: null,
        parseHash: L.Hash.parseHash,
        formatHash: L.Hash.formatHash,
        init: function(map) {
            this.map = map;
            this.lastHash = null;
            this.onHashChange();
            if (!this.isListening) { this.startListening() }
        },
        removeFrom: function(map) {
            if (this.changeTimeout) { clearTimeout(this.changeTimeout) }
            if (this.isListening) { this.stopListening() }
            this.map = null
        },
        onMapMove: function() {
            if (this.movingMap || !this.map._loaded) { return !1 }
            var hash = this.formatHash(this.map);
            if (this.lastHash != hash) {
                location.replace(hash);
                this.lastHash = hash
            }
        },
        movingMap: !1,
        update: function() {
            var hash = location.hash;
            if (hash === this.lastHash) { return }
            var parsed = this.parseHash(hash);
            if (parsed) {
                this.movingMap = !0;
                this.map.setView(parsed.center, parsed.zoom);
                this.movingMap = !1
            } else { this.onMapMove(this.map) }
        },
        changeDefer: 100,
        changeTimeout: null,
        onHashChange: function() {
            if (!this.changeTimeout) {
                var that = this;
                this.changeTimeout = setTimeout(function() {
                    that.update();
                    that.changeTimeout = null
                }, this.changeDefer)
            }
        },
        isListening: !1,
        hashChangeInterval: null,
        startListening: function() {
            this.map.on("moveend", this.onMapMove, this);
            if (HAS_HASHCHANGE) { L.DomEvent.addListener(window, "hashchange", this.onHashChange) } else {
                clearInterval(this.hashChangeInterval);
                this.hashChangeInterval = setInterval(this.onHashChange, 50)
            }
            this.isListening = !0
        },
        stopListening: function() {
            this.map.off("moveend", this.onMapMove, this);
            if (HAS_HASHCHANGE) { L.DomEvent.removeListener(window, "hashchange", this.onHashChange) } else { clearInterval(this.hashChangeInterval) }
            this.isListening = !1
        }
    };
    L.hash = function(map) { return new L.Hash(map) };
    L.Map.prototype.addHash = function() { this._hash = L.hash(this) };
    L.Map.prototype.removeHash = function() { this._hash.removeFrom() }
})(window);
// END MAP HASH FUNCTION

import { LMap, LTileLayer, LLayerGroup, LMarker, LPopup, LIcon } from 'vue2-leaflet';
import Vue2LeafletLocatecontrol from 'vue2-leaflet-locatecontrol/Vue2LeafletLocatecontrol';
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster';
import Multiselect from 'vue-multiselect';
import { OpenStreetMapProvider } from 'leaflet-geosearch';
import VGeosearch from 'vue2-leaflet-geosearch';

const provider = new OpenStreetMapProvider();

export default {
    props: ['map_id', 'map_token', 'users_can_create_incidents', 'map_categories'],
    components: { LMap, LTileLayer, LMarker, LPopup, 'l-locatecontrol': Vue2LeafletLocatecontrol, LIcon, 'l-marker-cluster': Vue2LeafletMarkerCluster, LLayerGroup, Multiselect, 'v-geosearch': VGeosearch },
    data() {
        return {
            center: L.latLng(43.7040, 7.3111),
            url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager_labels_under/{z}/{x}/{y}{r}.png',
            attribution: '&copy; <a href="https://cartes.io">Cartes.io</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            incidents: [],
            categories: this.map_categories ? this.map_categories : [],
            new_message: '',
            fullCategory: { id: null, name: '' },
            query: '',
            open_incident: null,
            markerResults: {
            },
            submit_data: {
                lat: 0,
                lng: 0,
                category: 0,
                category_name: '',
                loading: false,
                map_token: this.map_token
            },
            geosearchOptions: {
                // Important part Here
                provider: provider,
                style: 'button',
                autoClose: true,
                showPopup: true,
                showMarker: false,
                keepResult: true,
                marker: {
                    // icon: greenIcon,
                    draggable: false,
                },
            }
        }
    },
    mounted() {

        new L.Hash(this.$refs.map.mapObject);

        //Bounds set slightly higher than actual world max to create a "padding" on the map
        this.$refs.map.mapObject.setMaxBounds([
            [-90, -Infinity],
            [90, Infinity]
        ]);

        if (localStorage.getItem('map_' + this.map_id) && !this.submit_data.map_token) {
            this.submit_data.map_token = localStorage.getItem('map_' + this.map_id)
        } else if (this.submit_data.token) {
            localStorage['map_' + this.map_id] = this.submit_data.map_token
        }

        this.getIncidents()
        //this.getCategories()

        Echo.channel('maps.' + this.map_id).listen('IncidentCreated', (e) => {
            this.incidents.push(e.incident);
            this.new_message = "" + e.incident.category.name + "";
            setTimeout(function() {
                this.new_message = '';
            }.bind(this), 5000);
        });

    },
    computed: {
        incidentsCount() {
            return this.incidents.length;
        },
        canPost() {
            if (this.submit_data.map_token) {
                return 'yes'
            }
            return this.users_can_create_incidents;
        },
        activeIncidents() {
            return this.incidents.filter(function(incident) {
                if (incident.expires_at == null) {
                    return true
                }
                return Date() <= Date(Date.parse(incident.expires_at.replace(/-/g, '/')))
            })
        }
    },

    watch: {
        incidentsCount(newValue) {
            //$emit('incidents-count-change', newValue);
        }
    },
    created: function() {
        // _.debounce is a function provided by lodash to limit how
        // often a particularly expensive operation can be run.
        // In this case, we want to limit how often we access
        // yesno.wtf/api, waiting until the user has completely
        // finished typing before making the ajax request. To learn
        // more about the _.debounce function (and its cousin
        // _.throttle), visit: https://lodash.com/docs#debounce
        this.debouncedGetCategories = _.debounce(this.getCategories, 250)
        this.debouncedRenderTimeago = _.debounce(this.renderTimeago, 50)
    },
    methods: {
        addMarker(event) {
            this.$refs.hello_popup.mapObject.openPopup(event.latlng);
            if(!this.submit_data.category) {
                $('.multiselect').focus();
            }
            this.submit_data.lat = event.latlng.lat;
            this.submit_data.lng = event.latlng.lng;
        },
        async handleOpenedPopup(event, id){
            this.open_incident = this.incidents.findIndex((e) => e.id === id)
            if (this.incidents[this.open_incident] && this.incidents[this.open_incident].marker) {
                // console.log(this.incidents[this.open_incident].marker);
            } else {
                Vue.set(this.incidents[this.open_incident], 'marker', {label: "One sec, we're fetching the address..."})
                const results = await provider.search({ query: event.latlng.lat+" "+event.latlng.lng })
                Vue.set(this.incidents[this.open_incident], 'marker', results[0])
            }
            //this.$refs.hello_popup.mapObject.closePopup();
        },
        openPopup(event) {
            this.debouncedRenderTimeago();
        },
        renderTimeago() {
            timeago.render(document.querySelectorAll('.timestamp'))
        },
        addTag(newTag) {
            const tag = {
                category_id: -1,
                name: newTag,
                icon: '/images/marker-01.svg'
            }
            this.categories.push(tag)
            this.fullCategory = tag
            this.submit_data.category = tag.category_id
            this.submit_data.category_name = tag.name
            this.submitForm()
        },
        handleSelectInput(val) {
            //this.fullCategory = val
            this.submit_data.category = val.id
            this.submit_data.category_name = val.name
            this.submitForm()
        },
        inLocalStorageKey(id) {
            if (localStorage['post_' + id]) {
                return true
            }
            return false
        },
        asyncFind(query) {
            if (query.length < 3) { return false }
            this.submit_data.loading = true
            this.query = query
            this.debouncedGetCategories()

        },
        getIncidents() {
            axios
                .get('/api/maps/' + this.map_id + '/incidents')
                .then(response => (
                    this.incidents = response.data
                ))
        },
        getCategories(query) {
            axios
                .get('/api/categories?query=' + this.query)
                .then(response => {
                    this.categories = response.data
                    this.submit_data.loading = false
                })
        },
        deleteIncident(id) {
            this.submit_data.loading = true;
            axios
                .delete('/api/maps/' + this.map_id + '/incidents/' + id, { data: { token: localStorage['post_' + id] } })
                .then((res) => {
                    this.incidents = this.incidents.filter((e) => e.id !== id)
                    localStorage.removeItem('post_' + id)
                    this.submit_data.loading = false;
                });
        },
        submitForm() {
            this.submit_data.loading = true;
            axios
                .post('/api/maps/' + this.map_id + '/incidents', this.submit_data) // change this to post )
                .then((res) => {
                    this.$refs.hello_popup.mapObject.closePopup();
                    this.submit_data.loading = false
                    this.incidents.push(res.data);
                    localStorage['post_' + res.data.id] = res.data.token
                    dataLayer.push({ event: 'marker-create' });
                })
                .catch((error) => {
                    this.submit_data.loading = false
                    console.log(error.response);
                    if (error.response.data.errors) {
                        var message = Object.entries(error.response.data.errors)
                            .map(([error_name, error_value], i) => `${error_name}: ${error_value[0]} | `)
                            .join('\n');
                    } else {
                        var message = error.response.data.message
                    }
                    alert(message);
                });
        }
    }
}

</script>
<style>
@import "~leaflet.markercluster/dist/MarkerCluster.css";
@import "~leaflet.markercluster/dist/MarkerCluster.Default.css";
@import '~vue-multiselect/dist/vue-multiselect.min.css';
@import 'https://unpkg.com/leaflet-geosearch@2.6.0/assets/css/leaflet.css';

.map-notification {
    position: fixed;
    bottom: 1rem;
    z-index: 1002;
    left: 1rem;
    right: 1rem;
}

.your_custom_class .multiselect__option--highlight,
.your_custom_class .multiselect__option::after {
    background: var(--primary);
}

</style>
