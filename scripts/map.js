let stopsLayer;
let vehiclesLayer;
let bounds=[];
var map;

async function init() {
    let outerBox=document.getElementById('outerbox');
    outerBox.innerHTML='';

    // Create the container div for the map
    const container = document.createElement('div');
    container.setAttribute('style', 'width: 95%');
    outerBox.appendChild(container);

    // Create the div that will hold the Leaflet map
    const mapdiv = document.createElement('div');
    mapdiv.setAttribute('style', 'height: 550px');
    container.appendChild(mapdiv);

    // Create the map and fill it with tiles
    map = L.map(mapdiv);
    L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}', {
        attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
        maxZoom: 18,
        id: 'mapbox/streets-v11',
        tileSize: 512,
        zoomOffset: -1,
        accessToken: 'pk.eyJ1Ijoia2V2aW53MjQwMSIsImEiOiJja3I1ODZqdWszMmdqMnBwYW9qbWVnY2c4In0.qqgVHQu94DuWbLbgjWMN9w'
    }).addTo(map);

    // Fill the map with all routes
    for (let h=0;h<routes.length;h++){
        let route = routes[h];
        let shape=[];
        let currentShape=[];
        let routecolor=route['route_color'];
        if(routecolor==undefined){
            routecolor='aaaaaa';
        }
        for (let i=0;i<shapes.length;i++) {
            if(shapes[i]['route_id'] == route['route_id']){
                const point = [parseFloat(shapes[i]['shape_pt_lat']),parseFloat(shapes[i]['shape_pt_lon'])];
                shape.push(point);
                bounds.push(point);
                let dist=0;
                if(shape.length>1){
                    dist=L.latLng(shape[shape.length-2]).distanceTo(shape[shape.length-1]);
                }
                if((route['route_type']==2 && dist>10000) || (route['route_type']==3 && dist>1000) || (route['route_type']==0 && dist>1000)){
                    currentBranch=shapes[i]['route_id'];
                    L.polyline(currentShape,{color: `#${routecolor}`}).addTo(map);
                    console.log(dist);
                    currentShape=[];
                }else{
                    currentShape.push(point);
                }
            }
        }
        //L.polyline(shape,{color: `#${route[routes[0].indexOf('route_color')]}`}).addTo(map)
        L.polyline(currentShape,{color: `#${routecolor}`}).addTo(map);
    }
    map.fitBounds(L.latLngBounds(bounds));
    map.setZoom(9);
    //console.log(shapes);
    console.log('routes loaded');

    // Display stops if the map is sufficiently zoomed in
    stopsLayer=L.layerGroup();
    map.on('moveend', async () => {
        if(map.getZoom()>11){
            // Use map boundaries to calculate map width / height
            const latRange=(map.getBounds()._northEast.lat-map.getBounds()._southWest.lat)/2.0;
            const lngRange=(map.getBounds()._northEast.lng-map.getBounds()._southWest.lng)/2.0;
            // Fetch stops within map boundaries using AJAX
            const stops = await request(`/transitGTA-NY/utility/localstops.php`,
                `a=${agency}&lat=${map.getCenter().lat}&lng=${map.getCenter().lng}&latRange=${latRange}&lngRange=${lngRange}`)
                .then((response) => {return JSON.parse(response)});
            let stopmarkers=[];
            for(let i=0;i<stops.length;i++){
                let currentStop=stops[i];
                const cvs = document.createElement('canvas');
                cvs.setAttribute('style', 'height: 20px, width: 20px');
                const ctx = cvs.getContext('2d');
                ctx.fillStyle = '#0000ff';
                ctx.arc(10, 10, 9, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#000000';
                ctx.arc(10, 10, 9, 0, Math.PI * 2);
                ctx.stroke();
                const srcUrl = cvs.toDataURL();
                const circle = L.icon({ iconUrl: srcUrl, iconSize: [200, 100], iconAnchor: [10, 10], popupAnchor: [0, -9] });
                let pop=`<div style="font-size:20px;">
                    #${currentStop['stop_code']}: ${currentStop['stop_name']}<br>
                    <a href='./stopschedule.php?a=${agency}&s=${currentStop['stop_id']}'>
                        Stop schedule for this stop</a><br>`;
                stopmarkers.push(L.marker([currentStop['stop_lat'],currentStop['stop_lon']],{icon:circle}).bindPopup(pop));
                bounds.push([parseFloat(stops[i]['stop_lat']),parseFloat(stops[i]['stop_lon'])]);
            }
            map.removeLayer(stopsLayer);
            stopsLayer=L.layerGroup(stopmarkers);
            stopsLayer.addTo(map);
        }else if(map.getZoom()<=11&&map.hasLayer(stopsLayer)){
            map.removeLayer(stopsLayer);
            stopsLayer=L.layerGroup();
        }
    });
}

function zoomToCurrent(){
    // Zoom in to user's location
    navigator.geolocation.getCurrentPosition(zoomIn, zoomOut);
}
function zoomIn(position){
    //console.log(position.coords);
    // Add marker at user's location
    const cvs = document.createElement('canvas');
    cvs.setAttribute('style', 'height: 20px, width: 20px');
    const ctx = cvs.getContext('2d');
    ctx.fillStyle = '#0000ff';
    ctx.arc(10, 10, 9, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = '#ffffff';
    ctx.arc(10, 10, 9, 0, Math.PI * 2);
    ctx.stroke();
    const srcUrl = cvs.toDataURL();
    const circle = L.icon({ iconUrl: srcUrl, iconSize: [400, 200], iconAnchor: [10, 10], popupAnchor: [0, -9] });
    L.marker([position.coords.latitude,position.coords.longitude],{icon:circle}).bindPopup("Your current location").addTo(map);
    // Zoom to user's location
    map.setView([position.coords.latitude,position.coords.longitude],14);
}
function zoomOut(error){
    map.fitBounds(L.latLngBounds(bounds));
    map.setZoom(11);
    alert('Sorry, TransitGTA was unable to access your location. Please allow TransitGTA to see your location to use this feature.');
}

async function request(url, options) {
    let str='';
    let request = new XMLHttpRequest();
    request.open('POST', url, true);
    request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    request.responseType = 'text';
    request.onload = function () {
        str+=request.response;
    };
    request.send(options);
    return new Promise(resolve => {
        let interval=setInterval(() => {
            if(str.length>0){
                clearInterval(interval);
                //console.log(str);
                resolve(str);
            }
        }, 500);
    });
}

async function fileArray(fileName){
    const file = await fetch(`/${agency}/fileArray/${fileName}`).then((response) => {return response.json()}).then((json) => {return json.file});
    return file;
}

function findRow(table=[[]], searchColName='', searchStr=''){
    for(const row of table){
        if(row[table[0].indexOf(searchColName)]==searchStr){
            return row;
        }
    }
    return [];
}

init();