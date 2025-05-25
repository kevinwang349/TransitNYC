function displayTrip(){
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

    // Parse shape
    let shape=[];
    for (const point of tripshape) {
        shape.push([parseFloat(point['shape_pt_lat']), parseFloat(point['shape_pt_lon'])]);
    }
    L.polyline(shape,{color: `#${color}`}).addTo(map); // add trip shape to map

    for (const stop of tripstops){
        // Add stop to map
        const cvs = document.createElement('canvas');
        cvs.setAttribute('style', 'height: 20px, width: 20px');
        const ctx = cvs.getContext('2d');
        ctx.fillStyle = '#'+color;
        ctx.arc(10, 10, 9, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#000000';
        ctx.arc(10, 10, 9, 0, Math.PI * 2);
        ctx.stroke();
        const srcUrl = cvs.toDataURL();
        const circle = L.icon({ iconUrl: srcUrl, iconSize: [200, 100], iconAnchor: [10, 10], popupAnchor: [0, -9] });
        let pop=`<div style="font-size: 20px;">#${stop['stop_code']}: ${stop['stop_name']}
            <br><a href="./stopschedule?s=${stop['stop_id']}">Stop schedule for this stop</a></div>`;
        L.marker([stop['stop_lat'],stop['stop_lon']],{icon:circle}).addTo(map).bindPopup(pop);
        //shape.push([tripstops[tripstops[0].indexOf('stop_lat')],tripstops[tripstops[0].indexOf('stop_lon')]]);
        //const sender=`#${tripstops[stops[0].indexOf('stop_code')]} ${tripstops[stops[0].indexOf('stop_name')]} at ${arrivalTimes[i]}`;
        //console.log(sender);
    }
    if(shape.length==0){
        shape=[[40.21549648441435, -74.76579380336543],[41.69582896785678, -73.92293054376148],[40.88800029581999, -72.47977206993964]];
    }
    map.fitBounds(L.latLngBounds(shape));
}
displayTrip();