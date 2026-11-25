describe('RebelMyForms', function () {
    this.fixture = 'Piwik\\Plugins\\RebelMyForms\\tests\\Fixtures\\SiteWithFormVisits';

    const params = 'idSite=1&period=day&date=2024-03-05';
    const pageUrl = `?module=CoreHome&action=index&${params}#?${params}`
        + '&category=RebelMyForms_Forms&subcategory=General_Overview';
    const forms = '.dataTable[data-report="RebelMyForms.getForms"]';

    before(function () {
        testEnvironment.pluginsToLoad = ['RebelMyForms'];
        testEnvironment.save();
    });

    function cellsOfRows(selector) {
        return page.evaluate((rows) => [...document.querySelectorAll(rows)]
            .map((row) => [...row.querySelectorAll('td')].map((cell) => cell.textContent.trim())), selector);
    }

    it('should show each form with how often it was seen, started and submitted', async function () {
        await page.goto(pageUrl);
        await page.waitForSelector(`${forms} tbody tr`);
        await page.waitForNetworkIdle();

        const rows = await cellsOfRows(`${forms} tr.subDataTable`);
        expect(rows).to.deep.equal([['contact', '3', '2', '66.67%', '1', '50%', '-', '-', '4s', '7s']]);
    });

    it('should show the fields of a form when it is clicked', async function () {
        await page.click(`${forms} tr.subDataTable td.label`);
        await page.waitForSelector(`${forms} .cellSubDataTable tbody tr`);
        await page.waitForNetworkIdle();

        const rows = await cellsOfRows(`${forms} .cellSubDataTable tbody tr`);
        // In the order of the form.
        expect(rows.map((row) => row[0])).to.deep.equal(['name', 'email', 'phone']);
        // Place, used by, time, hesitation, changes, returns, left empty, refused, drop-offs, drop-off rate.
        expect(rows[1].slice(1)).to.deep.equal(['2', '2', '3.5s', '1s', '4', '2', '-', '2', '1', '50%']);
    });

    it('should show the pages the forms are on', async function () {
        const rows = await cellsOfRows('.dataTable[data-report="RebelMyForms.getPages"] tbody tr');
        expect(rows.map((row) => row.slice(0, 2))).to.deep.equal([['http://example.org/contact', '3']]);
    });

    it('should show the kinds of device the forms were seen on', async function () {
        const rows = await cellsOfRows('.dataTable[data-report="RebelMyForms.getDevices"] tbody tr');
        expect(rows.length).to.equal(1);
        expect(rows[0][1]).to.equal('3');
    });

    it('should list the fields of all forms with the one where most gave up first', async function () {
        const rows = await cellsOfRows('.dataTable[data-report="RebelMyForms.getAllFields"] tbody tr');
        expect(rows.map((row) => row[0])).to.deep.equal(['contact › email', 'contact › name', 'contact › phone']);
    });

    it('should show the documentation', async function () {
        await page.goto('?module=RebelMyForms&action=documentation&idSite=1&period=day&date=2024-03-05');
        await page.waitForSelector('.rebelMyFormsDocumentation');

        const text = await page.evaluate(() => document.querySelector('.rebelMyFormsDocumentation').textContent);
        expect(text).to.contain('What counts as submitted');
    });
});
