import { readFileSync, writeFileSync } from 'fs';
const agency='mnr';

function initGO() {
    const routes = fileArray('routes.txt')
    const trips = fileArray('trips.txt')
    const stops = fileArray('stops.txt')
    const times = fileArray('stop_times.txt')
    const routestopsheader = stops[0].slice().unshift('route_id');
    const routestops=[routestopsheader];
    const routestops2=[['route_id','route_short_name','stop_ids']];

    const newtrips=trips;
    for(const trip of trips){
        if(trip[trips[0].indexOf('service_id')]=='20231026'||trip[trips[0].indexOf('service_id')]=='20231027'){
            newtrips.push(trip);
        }
    }
    const newtimes=times;
    for(const time of times){
        if(time[times[0].indexOf('trip_id')].includes('20231026')||time[times[0].indexOf('trip_id')].includes('20231027')){
            newtimes.push(time);
        }
    }
    console.log(newtrips.length);
    console.log(newtimes.length);

    for(let i=1;i<routes.length;i++){
        const tripids=[];
        for(let j=1;j<newtrips.length;j++){
            if(newtrips[j][newtrips[0].indexOf('route_id')]==routes[i][routes[0].indexOf('route_id')]){
                tripids.push(newtrips[j][newtrips[0].indexOf('trip_id')]);
            }
        }
        const stopids=[routes[i][routes[0].indexOf('route_id')],routes[i][routes[0].indexOf('route_short_name')]];
        for(let j=1;j<newtimes.length;j++){
            const stopid = newtimes[j][newtimes[0].indexOf('stop_id')];
            if(tripids.includes(newtimes[j][newtimes[0].indexOf('trip_id')])&&!stopids.includes(stopid)){
                stopids.push(stopid);
                let stop=findRow(stops,'stop_id',stopid);
                if(stop.length==0) {/*console.log(stopid); console.log(findRow(stops,'stop_code',stopid));*/ continue;}
                stop.unshift(routes[i][routes[0].indexOf('route_id')],routes[i][routes[0].indexOf('route_short_name')]);
                routestops.push(stop);
            }
        }
        routestops2.push(stopids);
        console.log(routes[i][routes[0].indexOf('route_long_name')]);
    }
    
    arrayFile('routestops.txt', routestops);
    arrayFile('routestopids.txt', routestops2);

    /*for(const stoptime of newtimes){
        const trip=findRow(trips,'trip_id',stoptime[times[0].indexOf('trip_id')]);
        //const route=findRow(routes,'route_id',trip[trips[0].indexOf('route_id')]);
        let stop=findRow(stops,'stop_id',stoptime[times[0].indexOf('stop_id')]);
        stop.unshift(trip[trips[0].indexOf('route_id')]);
        console.log(stop);
        routestops.push(stop);
    }
    arrayFile('routestops.txt', routestops);*/
}


function init() {
    const routes = fileArray('routes.txt')
    const trips = fileArray('trips.txt')
    const stops = fileArray('stops.txt')
    const times = fileArray('stop_times.txt')
    const routestopsheader = stops[0].slice().unshift('route_id');
    const routestops=[routestopsheader];
    const routestops2=[['route_id','stop_ids']];

    const newtrips=trips;
    /*for(const trip of trips){
        if(trip[trips[0].indexOf('service_id')]=='20231026'||trip[trips[0].indexOf('service_id')]=='20231027'){
            newtrips.push(trip);
        }
    }*/
    const newtimes=times;
    /*for(const time of times){
        if(time[times[0].indexOf('trip_id')].includes('20231026')||time[times[0].indexOf('trip_id')].includes('20231027')){
            newtimes.push(time);
        }
    }*/
    console.log(newtrips.length);
    console.log(newtimes.length);

    for(let i=1;i<routes.length;i++){
        const tripids=[];
        for(let j=1;j<newtrips.length;j++){
            if(newtrips[j][newtrips[0].indexOf('route_id')]==routes[i][routes[0].indexOf('route_id')]){
                tripids.push(newtrips[j][newtrips[0].indexOf('trip_id')]);
            }
        }
        console.log(tripids.length);
        const stopids=[routes[i][routes[0].indexOf('route_id')]];
        for(let j=1;j<newtimes.length;j++){
            const stopid = newtimes[j][newtimes[0].indexOf('stop_id')];
            if(tripids.includes(newtimes[j][newtimes[0].indexOf('trip_id')])&&!stopids.includes(stopid)){
                stopids.push(stopid);
                let stop=findRow(stops,'stop_id',stopid);
                if(stop.length==0) {/*console.log(stopid); console.log(findRow(stops,'stop_code',stopid));*/ continue;}
                stop.unshift(routes[i][routes[0].indexOf('route_id')]);
                routestops.push(stop);
            }
        }
        routestops2.push(stopids);
        console.log(routes[i][routes[0].indexOf('route_long_name')]);
    }
    
    arrayFile('routestops.txt', routestops);
    arrayFile('routestopids.txt', routestops2);

    /*for(const stoptime of newtimes){
        const trip=findRow(trips,'trip_id',stoptime[times[0].indexOf('trip_id')]);
        //const route=findRow(routes,'route_id',trip[trips[0].indexOf('route_id')]);
        let stop=findRow(stops,'stop_id',stoptime[times[0].indexOf('stop_id')]);
        stop.unshift(trip[trips[0].indexOf('route_id')]);
        console.log(stop);
        routestops.push(stop);
    }
    arrayFile('routestops.txt', routestops);*/
}

function fileArray(filename) {
    const response = readFileSync('../gtfs/'+agency+'/' + filename);
    const txt = new TextDecoder("utf-8").decode(response);
    const array1 = txt.split("\r\n");
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
            console.log(row);
        }
    }
    let str = array2.join('\r\n');
    writeFileSync('../gtfs/'+agency+'/' + filename, str);
}

init();

function findRow(table=[[]], searchColName='', searchStr=''){
    for(const row of table){
        if(row[table[0].indexOf(searchColName)]==searchStr){
            return row;
        }
    }
    return [];
}