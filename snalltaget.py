import requests
import re
import json
import tornado.ioloop
import tornado.web
from threading import Thread

cache = {}

class MainHandler(tornado.web.RequestHandler):
	@tornado.web.asynchronous
	def get(self):
		Thread(target=self.makerequest).start()
		
	def returnrequest(self, data):
		tornado.ioloop.IOLoop.instance().add_callback(self.returndata, data)
	
	def makerequest(self):
		global cache
		r = requests.get('https://boka.snalltaget.se/boka-biljett')
		cookie = r.cookies["Token"]
		cookies = dict(Token=cookie)

		query = json.loads('{"DepartureLocationId":1,"DepartureLocationProducerCode":74,"ArrivalLocationId":110,"ArrivalLocationProducerCode":74,"DepartureDateTime":"2014-02-21 12:00","TravelType":"E","Passengers":[{"PassengerCategory":"VU"}]}')
		#query['DepartureDateTime'] = self.get_argument('date','2014-02-20') + self.get_argument('departureTime','12:00')
		headers = {'content-type': 'application/json'}

		r = requests.post('https://boka.snalltaget.se/api/timetables', data=json.dumps(query), headers=headers, cookies=cookies)

		trips = r.json()
		
		if len(trips['JourneyAdvices']) > 10:
			max = 10
		else:
			max = len(trips['JourneyAdvices']-1)
			
		pquery = {'TimetableId':trips['Id'], 'JourneyConnectionReferences':[]}

		for i in range(0, max):
			pquery['JourneyConnectionReferences'].append(trips['JourneyAdvices'][i]['JourneyConnectionReference'])

		r = requests.post('https://boka.snalltaget.se/api/journeyadvices/lowestprices', data=json.dumps(pquery), headers=headers, cookies=cookies)

		price = r.json()

		for i in range(0, len(trips['JourneyAdvices'])):
			for j in range (0,len(price)):
				if price[j]['JourneyConnectionReference'] == trips['JourneyAdvices'][i]['JourneyConnectionReference']:
					trips['JourneyAdvices'][i]['IsSleeperTrain'] = price[j]['IsSleeperTrain']
					trips['JourneyAdvices'][i]['LowestTotalPrice'] = price[j]['LowestTotalPrice']
					trips['JourneyAdvices'][i]['Currency'] = price[j]['Currency']
					stopfrom = str(trips['JourneyAdvices'][i]['DepartureLocation']['ProducerCode']*100000 + trips['JourneyAdvices'][i]['DepartureLocation']['LocationId'])
					stopto = str(trips['JourneyAdvices'][i]['ArrivalLocation']['ProducerCode']*100000 +trips['JourneyAdvices'][i]['ArrivalLocation']['LocationId'])
					timefrom = trips['JourneyAdvices'][i]['DepartureDateTime']
					timeto = trips['JourneyAdvices'][i]['ArrivalDateTime']
					cache[stopfrom+timefrom+stopto+timeto] = trips['JourneyAdvices'][i]
					break
		print cache
		self.returnrequest(trips)
		
	def returndata(self, trips):
		self.write(trips)
		self.finish()

application = tornado.web.Application([
    (r"/snalltaget/", MainHandler),
])

application.listen(8888)
tornado.ioloop.IOLoop.instance().start()