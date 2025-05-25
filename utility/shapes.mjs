import { readFileSync, writeFileSync } from 'fs';
const agency='lirr';

function init() {
    const routes = fileArray('routes.txt')
    const trips = fileArray('trips.txt')
    const shapes = fileArray('shapes.txt')
    let routelats = [];
    let routelons = [];
    let branches = [];
    let shapeids = [];
    for (let i = 1; i < routes.length; i++) {
        routelats.push([routes[i][routes[0].indexOf('route_id')]])
        routelons.push([routes[i][routes[0].indexOf('route_id')]])
        branches.push([routes[i][routes[0].indexOf('route_id')]])
        shapeids.push([routes[i][routes[0].indexOf('route_id')]])
    }
    for (let i = 1; i < trips.length; i++) {
        let shapeid = trips[i][trips[0].indexOf('shape_id')]
        let routeid = trips[i][trips[0].indexOf('route_id')]
        for (let j = 0; j < shapeids.length; j++) {
            const route = shapeids[j];
            if (route[0] == routeid) {
                if (!route.includes(shapeid)) {
                    route.push(shapeid);
                }
                break;
            }
        }
    }
    console.log(shapes.length);
    let branchctr = 0;
    for (let i = 1; i < shapes.length; i++) {
        const shapeid = shapes[i][shapes[0].indexOf('shape_id')];
        for (let j = 0; j < shapeids.length; j++) {
            if (shapeids[j].includes(shapeid)) {
                const routelat = routelats[j];
                const routelon = routelons[j];
                const branch = branches[j];
                for (let k = i; k < shapes.length; k++) {
                    const lat = shapes[k][shapes[0].indexOf('shape_pt_lat')]
                    const lon = shapes[k][shapes[0].indexOf('shape_pt_lon')]
                    if (shapes[k][shapes[0].indexOf('shape_id')] != shapeid) {
                        branchctr++;
                        break;
                    }
                    i++;
                    if (!routelat.includes(lat) && !routelon.includes(lon)) {
                        routelat.push(lat);
                        routelon.push(lon);
                        branch.push(branchctr);
                    }
                }
                break;
            }
        }
    }
    console.log(routelats.length);
    let newshapes = [['route_id', 'shape_pt_lat', 'shape_pt_lon']];
    for (let i = 0; i < routelats.length; i++) {
        const routelat = routelats[i];
        const routelon = routelons[i];
        const branch = branches[i];
        for (let j = 1; j < routelat.length; j++) {
            newshapes.push([branch[0], routelat[j], routelon[j], branch[j]]);
        }
    }
    arrayFile('routeshapes.txt', newshapes);
}

function fileArray(filename) {
    const response = readFileSync('../gtfs/'+agency+'/' + filename);
    const txt = new TextDecoder("utf-8").decode(response);
    const array1 = txt.split("\n");
    let array2 = [];
    for (const arr of array1) {
        if (arr.length > 0) {
            let row = arr.split(',');
            for (let j=0;j<row.length;j++) {
                if (row[j].substring(0, 1) == '"' && row[j].substring(row[j].length-1) == '"') {
                    row[j] = row[j].substring(1, row[j].length-1);
                }
            }
            array2.push(row);
        }
    }
    return array2;
}

function arrayFile(filename, array) {
    let array2 = []
    for (const row of array) {
        try {
            array2.push(row.join(','))
        } catch {
            console.log(array);
        }
    }
    let str = array2.join('\r\n');
    writeFileSync('../gtfs/'+agency+'/' + filename, str);
}

init();

function f() {

    let directions = [{}];
    // Add the route's path to the map
    request('https://retro.umoiq.com/service/publicJSONFeed?command=routeConfig&a=ttc&r=' + routeid).then((response) => {
        const json = JSON.parse(response);
        directions = json.direction;
        let path = json.route.path;
        for (let i = 0; i < path.length; i++) {
            let points = path.point;
            let shape = [];
            for (const point of points) {
                shape.push([point.lat, point.lon]);
            }
            L.polyline(shape, { color: `#${route[routes[0].indexOf('route_color')]}` }).addTo(map);
        }
        map.fitBounds(L.latLngBounds([[json.latMin, json.lonMin], [json.latMiaxjson.lonMax]]));
    });

    function f1() {
        const json = JSON.parse(response);
        const vehicles = json.vehicle;
        let popup = `Vehicle ${vehicle.id} on route `;
        for (const vehicle of vehicles) {
            if (vehicle.routeTag == routeid) {
                const canvas = document.createElement('canvas');
                canvas.setAttribute('style', 'height: 30px, width: 30px');
                const context = canvas.getContext('2d');
                for (const direction of directions) {
                    if (vehicle.dirTag == direction.tag) {
                        popup += `${direction.title}`;
                    }
                }
                context.fillStyle = '#777777';
                context.arc(15, 15, 14, 0, Math.PI * 2);
                context.fill();
                context.fillStyle = '#000000';
                context.arc(15, 15, 14, 0, Math.PI * 2);
                context.stroke();
                context.fillStyle = '#ffffff';
                context.font = '9px sans-serif';
                context.textAlign = 'center';
                context.textBaseline = 'middle';
                context.fillText(i, 15, 15);
                const src = canvas.toDataURL();
                const icon = L.icon({ iconUrl: src, iconSize: [300, 150], iconAnchor: [15, 15], popupAnchor: [0, -14] });
                L.marker([vehicle.lat, vehicle.lon], { icon: icon }).addTo(map).bindPopup(popup);
            }
        }
    }
}