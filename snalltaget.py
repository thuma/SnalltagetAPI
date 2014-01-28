import requests
import re
import json

r = requests.get('https://boka.snalltaget.se/boka-biljett')
cookie = r.cookies["Token"]
cookies = dict(Token=cookie)

query = json.loads('{"DepartureLocationId":1,"DepartureLocationProducerCode":74,"ArrivalLocationId":110,"ArrivalLocationProducerCode":74,"DepartureDateTime":"2014-02-21 12:00","TravelType":"E","Passengers":[{"PassengerCategory":"VU"}]}')

headers = {'content-type': 'application/json'}

r = requests.post('https://boka.snalltaget.se/api/timetables', data=json.dumps(query), headers=headers, cookies=cookies)

trips = r.json()

query = {'TimetableId':trips['Id'], 'JourneyConnectionReferences':[]}

if len(trips['JourneyAdvices']) > 10:
	max = 10
else:
	max = len(trips['JourneyAdvices'])

for i in range(0, max):
   query['JourneyConnectionReferences'].append(trips['JourneyAdvices'][i]['JourneyConnectionReference'])


r = requests.post('https://boka.snalltaget.se/api/journeyadvices/lowestprices', data=json.dumps(query), headers=headers, cookies=cookies)

price = r.json()

for i in range(0, len(trips['JourneyAdvices'])):
	for j in range (0,len(price)):
		if price[j]['JourneyConnectionReference'] == trips['JourneyAdvices'][i]['JourneyConnectionReference']:
			trips['JourneyAdvices'][i]['IsSleeperTrain'] = price[j]['IsSleeperTrain']
			trips['JourneyAdvices'][i]['LowestTotalPrice'] = price[j]['LowestTotalPrice']
			trips['JourneyAdvices'][i]['Currency'] = price[j]['Currency']
			break

print json.dumps(trips)
   